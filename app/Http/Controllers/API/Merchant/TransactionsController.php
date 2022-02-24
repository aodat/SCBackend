<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Utilities\Documents;
use App\Http\Controllers\Utilities\InvoiceService;
use App\Http\Requests\Merchant\TransactionRequest;
use App\Models\Merchant;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Libs\Dinarak;

class TransactionsController extends MerchantController
{
    private $type = [
        'ALL' => 0, 'CASHIN' => 0, 'CASHOUT' => 0,
    ];

    public function index(TransactionRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $types = $filters['types'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $sources = $filters['sources'] ?? [];
        $amount = $filters['amount']['val'] ?? null;
        $operation = $filters['amount']['operation'] ?? null;
        $subtype = $filters['subtype'] ?? null;

        $transaction = Transaction::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($statuses)) {
            $transaction->whereIn('status', $statuses);
        }

        if (count($sources)) {
            $transaction->whereIn('source', $sources);
        }

        if (count($types)) {
            $transaction->whereIn('type', $types);
        }

        if ($subtype != '*') {
            $transaction->where('subtype', $subtype);
        }

        if ($operation) {
            $transaction->where('amount', $operation, $amount);
        } else if ($amount) {
            $transaction->whereBetween('amount', [intval($amount), intval($amount) . '.99']);
        }

        if (in_array('CASHIN', $types)  && $subtype == 'COD') {
            return $this->response($transaction->get(), 'Data Retrieved Successfully');
        } else {
            $tabs = DB::table('transactions')
                ->where('merchant_id', $request->user()->merchant_id);

            if ($subtype && $subtype != '*') {
                $tabs->where('subtype', $subtype);
            }

            $tabs = $tabs->select('type', DB::raw(
                'count(type) as counter'
            ))
                ->groupBy('type')
                ->pluck('counter', 'type');

            $tabs = collect($this->type)->merge(collect($tabs));
            $tabs['ALL'] = $tabs['CASHIN'] + $tabs['CASHOUT'];
            return $this->pagination($transaction->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
        }
    }

    public function byDates(TransactionRequest $request)
    {
        $merchant_id = $request->user()->merchant_id;
        $filters = $request->json()->all();
        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');
        $type = $filters['type'] ?? null;

        $start = (request()->per_page ?? 30) * ($request->page ?? 0);

        $cashin = DB::table(DB::raw('transactions t'))
            ->select(
                DB::raw('date(created_at) as date'),
                'type',
                DB::raw('count(item_id) as number_shipment'),
                DB::raw('(
                        select t2.balance_after 
                        from transactions t2 
                        where t2.id = max(t.id)
                    ) as balance_after'),
                DB::raw('sum(amount) as amount')
            )
            ->where(function ($query) use ($request) {
                $query->where('source', 'SHIPMENT')
                    ->where('subtype', $request->subtype ?? 'COD')
                    ->where('type', 'CASHIN');
            })
            ->whereNotNull('item_id')
            ->where('merchant_id', '=', $merchant_id)
            ->groupByRaw('date(t.created_at)');

        $cashout = DB::table(DB::raw('transactions t'))
            ->select(
                DB::raw('date(created_at) as date'),
                'type',
                'item_id',
                'balance_after',
                'amount'
            )
            ->where(function ($query) use ($request) {
                $query->where('subtype', $request->subtype ?? 'COD')
                    ->where('type', 'CASHOUT');
            })
            ->where('merchant_id', '=', $merchant_id)
            ->groupByRaw('date(t.created_at)');

        if ($type == 'CASHIN')
            $allTransaction = DB::table($cashin->orderBy('date'))
                ->select('*', DB::raw($start . ' + ROW_NUMBER() OVER(ORDER BY date DESC) AS id'))
                ->whereBetween('date', [$since, $until])
                ->paginate(request()->per_page ?? 30);
        else if ($type == 'CASHOUT')

            $allTransaction = DB::table($cashout->orderBy('date'))
                ->select('*', DB::raw($start . ' + ROW_NUMBER() OVER(ORDER BY date DESC) AS id'))
                ->whereBetween('date', [$since, $until])
                ->paginate(request()->per_page ?? 30);
        else
            $allTransaction = DB::table($cashin->union($cashout)->orderBy('date'))
                ->select('*', DB::raw($start . ' + ROW_NUMBER() OVER(ORDER BY date DESC) AS id'))
                ->whereBetween('date', [$since, $until])
                ->paginate(request()->per_page ?? 30);

        
        $tabsTransaction = DB::table($cashin->union($cashout)->orderBy('date'))
            ->select('*', DB::raw($start . ' + ROW_NUMBER() OVER(ORDER BY date DESC) AS id'))
            ->paginate(request()->per_page ?? 30);


        
        $types = collect($tabsTransaction->toArray()['data'])->groupBy('type');

        $tabs['CASHIN'] = count($types['CASHIN'] ?? []);
        $tabs['CASHOUT'] = count($types['CASHOUT'] ?? []);
        $tabs['ALL'] = $tabs['CASHIN'] + $tabs['CASHOUT'];
        return $this->pagination($allTransaction, ['tabs' => $tabs]);
    }

    public function show($id, TransactionRequest $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function export(TransactionRequest $request)
    {
        $merchentID = $request->user()->merchant_id;
        $subtype = $request->subtype;
        $type = $request->type;

        $since = $request->created_at['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $request->created_at['until'] ?? Carbon::today()->format('Y-m-d');

        $transaction = Transaction::where('merchant_id', $merchentID)
            ->whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);

        if ($subtype != '*') {
            $transaction->where('subtype', $subtype);
        }

        $transactions = $transaction->get();

        $path = "export/transaction-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = Documents::xlsx(new TransactionsExport($transactions), $path);
        } else {
            $url = Documents::pdf('transactions', $path, $transactions);
        }

        return $this->response(['link' => $url], 'Data Retrieved Successfully', 200);
    }

    public function withDraw(TransactionRequest $request)
    {
        $merchecntInfo = $this->getMerchentInfo();

        if ($merchecntInfo->cod_balance <= 0) {
            return $this->error('The COD Balance Is Zero', 400);
        }

        $paymentMethod = $merchecntInfo->payment_methods;
        $payment = collect($paymentMethod)->where('id', $request->payment_method_id)->first();
        if ($payment == null) {
            throw new InternalException('Invalid Payment Method ID');
        }

        $dedaction = ($merchecntInfo->cod_balance <= 1000) ? $merchecntInfo->cod_balance : 1000;

        if ($merchecntInfo->cod_balance <= 0 || $dedaction > $merchecntInfo->cod_balance) {
            return $this->error('You dont have COD Balance');
        } else if ($merchecntInfo->cod_balance < 10) {
            return $this->error('The minimum withdrawal amount is 10 ' . $merchecntInfo->country_code);
        }

        $this->COD(
            'CASHOUT',
            $request->user()->merchant_id,
            null,
            $dedaction,
            'NONE',
            Request()->user()->id,
            'WithDraw Request',
            'PROCESSING',
            'WEB',
            $payment
        );

        return $this->successful('WithDraw Transaction Under Proocssing');
    }

    public function depositwRequest(TransactionRequest $request, Dinarak $dinarak)
    {
        $dinarak->pincode($request->wallet_number, $request->amount);
        $this->successful('Check Your OTP');
    }

    public function deposit(TransactionRequest $request, Dinarak $dinarak)
    {
        $merchecntInfo = $this->getMerchentInfo();
        $dinarak->deposit($merchecntInfo, $request->wallet_number, $request->amount, $request->pincode);

        $this->BUNDLE(
            'CASHIN',
            $request->user()->merchant_id,
            null,
            $request->amount,
            'CREDITCARD',
            $request->user()->id,
            'Deposit To ShipCash',
            'COMPLETED',
            Request()->header('agent') ?? 'API',
        );

        return $this->successful('Deposit Successfully');
    }

    public function transfer(TransactionRequest $request)
    {
        return $this->error('This Service Was Stopped Try Again Later');
        $merchecnt = $this->getMerchantInfo();
        if ($merchecnt->cod_balance >= $request->amount) {
            $merchantID = $request->user()->merchant_id;
            $createdBY = $request->user()->id;
            $amount = $request->amount;

            $this->BUNDLE(
                'CASHIN',
                $merchantID,
                null,
                $amount,
                'NONE',
                $createdBY,
                'Money Received from COD Balance',
                'COMPLETED',
                'WEB'
            );

            $this->COD(
                'CASHOUT',
                $merchantID,
                null,
                $amount,
                'NONE',
                $createdBY,
                'Money Received from COD Balance',
                'COMPLETED',
                'WEB'
            );

            return $this->successful('The Amount Transferred Successfully');
        }
        return $this->error('The COD Balance Is Not Enough', 400);
    }

    public function COD($type = 'CASHIN', $merchant_id, $awb, $amount, $source, $created_by, $description = '', $status = 'COMPLETED', $resource = 'API', $payment_method = null)
    {
        $merchant = Merchant::findOrFail($merchant_id);
        if ($type == 'CASHIN') {
            $merchant->cod_balance += $amount;
        } else {
            $merchant->cod_balance -= $amount;
        }

        $merchant->save();

        return Transaction::create(
            [
                'type' => $type,
                'subtype' => 'COD',
                'item_id' => $awb,
                'merchant_id' => $merchant_id,
                'description' => $description,
                'balance_after' => $merchant->cod_balance,
                'amount' => $amount,
                'source' => $source,
                'status' => $status,
                'created_by' => $created_by,
                'resource' => $resource,
                'payment_method' => collect($payment_method),
            ]
        )->id;
    }

    public function BUNDLE($type = 'CASHIN', $merchant_id, $item_id = null, $amount, $source, $created_by, $description = '', $status = 'COMPLETED', $resource = 'API', $payment_method = null)
    {
        $merchant = Merchant::findOrFail($merchant_id);
        if ($type == 'CASHIN') {
            $merchant->bundle_balance += $amount;
        } else {
            $merchant->bundle_balance -= $amount;
        }
        $merchant->save();

        $transaction = Transaction::create(
            [
                'type' => $type,
                'subtype' => 'BUNDLE',
                'item_id' => $item_id,
                'merchant_id' => $merchant_id,
                'description' => $description,
                'balance_after' => $merchant->bundle_balance,
                'amount' => $amount,
                'source' => $source,
                'status' => $status,
                'created_by' => $created_by,
                'resource' => $resource,
                'payment_method' => collect($payment_method),
            ]
        );

        if ($type == 'CASHIN') {
            $transaction->url = InvoiceService::invoice($merchant_id, rand(1, 999), $amount, $description);
            $transaction->save();
        }
        return $transaction->id;
    }
}
