<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Utilities\Documents;
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

    private $subType = [
        'ALL' => 0, 'COD' => 0, 'BUNDLE' => 0,
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

        if ($subtype && $subtype != '*') {
            $transaction->where('subtype', $subtype);
        }

        if ($operation) {
            $transaction->where('amount', $operation, $amount);
        } else if ($amount) {
            $transaction->whereBetween('amount', [intval($amount), intval($amount) . '.99']);
        }

        $tabs = DB::table('transactions')
            ->where('merchant_id', Request()->user()->merchant_id);

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

    public function show($id, TransactionRequest $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function withDraw(TransactionRequest $request, Dinarak $dinarak)
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
        }

        $result = $dinarak->withdraw($merchecntInfo, $payment['iban'], $dedaction);

        $merchecntInfo->cod_balance -= $merchecntInfo->amount;
        $merchecntInfo->save();

        Transaction::create([
            "type" => "CASHOUT",
            "subtype" => "COD",
            "item_id" => null,
            "created_by" => Request()->user()->id,
            "merchant_id" => Request()->user()->merchant_id,
            'amount' => $dedaction,
            'notes' => json_encode($result),
            'status' => 'PROCESSING',
            "balance_after" => $merchecntInfo->cod_balance - $dedaction,
            "source" => "NONE",
        ]);
        return $this->successful('WithDraw Transaction Completed');
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

        Transaction::create(
            [
                'type' => 'CASHIN',
                'subtype' => 'BUNDLE',
                'merchant_id' => $request->user()->merchant_id,
                'source' => 'CREDITCARD',
                'status' => 'COMPLETED',
                'created_by' => $request->user()->id,
                'balance_after' => $request->amount + $merchecntInfo->bundle_balance,
                'amount' => $request->amount,
                'resource' => Request()->header('agent') ?? 'API',
            ]
        );

        $merchecntInfo->bundle_balance = $request->amount + $merchecntInfo->bundle_balance;
        $merchecntInfo->save();

        return $this->successful('Deposit Sucessfully');
    }

    public function transfer(TransactionRequest $request)
    {

        return $this->error('Unexpected Error');
        $merchecnt = $this->getMerchantInfo();
        if ($merchecnt->cod_balance >= $request->amount) {

            Transaction::insert([
                [
                    'type' => 'CASHIN',
                    'subtype' => 'BUNDLE',
                    'merchant_id' => $request->user()->merchant_id,
                    'source' => 'ORDER',
                    'status' => 'COMPLETED',
                    'created_by' => $request->user()->id,
                    'balance_after' => $request->amount + $merchecnt->bundle_balance,
                    'amount' => $request->amount,
                    'resource' => Request()->header('agent') ?? 'API',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'type' => 'CASHOUT',
                    'subtype' => 'COD',
                    'merchant_id' => $request->user()->merchant_id,
                    'source' => 'ORDER',
                    'status' => 'COMPLETED',
                    'created_by' => $request->user()->id,
                    'balance_after' => (($merchecnt->cod_balance - $request->amount) > 0) ? $merchecnt->cod_balance - $request->amount : 0,
                    'amount' => $request->amount,
                    'resource' => Request()->header('agent') ?? 'API',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ]);

            $merchecnt->cod_balance -= $request->amount;
            $merchecnt->bundle_balance += $request->amount;
            $merchecnt->save();

            return $this->successful('The Amount Transferred Successfully');
        }
        return $this->error('The COD Balance Is Not Enough', 400);
    }

    public function export(TransactionRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $subtype = $request->subtype;
        $type = $request->type;
        $date = $request->date;

        $transaction = Transaction::where('merchant_id', $merchentID)
            ->whereDate('created_at', $date);

        if ($subtype && $subtype != '*') {
            $transaction->where('subtype', $subtype);
        }

        $transactions = $transaction->get();

        $path = "export/transaction-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = Documents::xlsx(new TransactionsExport($transactions), $path);
        } else {
            $url = Documents::pdf('transactions', $path, $transactions);
        }

        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }

    public function cashinCOD($merchant_id, $awb, $amount, $source, $created_by, $description = '', $status = 'COMPLETED')
    {
        $merchant = Merchant::findOrFail($merchant_id);
        $merchant->cod_balance += $amount;
        $merchant->save();

        return Transaction::create(
            [
                'type' => 'CASHIN',
                'subtype' => 'COD',
                'item_id' => $awb,
                'merchant_id' => $merchant_id,
                'description' => $description,
                'balance_after' => $merchant->cod_balance,
                'amount' => $amount,
                'source' => $source,
                'status' => $status,
                'created_by' => $created_by,
            ]
        )->id;
    }
}
