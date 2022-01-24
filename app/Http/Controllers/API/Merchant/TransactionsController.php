<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Exports\TransactionsExport;
use App\Http\Requests\Merchant\TransactionRequest;
use App\Jobs\WithDrawPayments;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Libs\Stripe;

class TransactionsController extends MerchantController
{

    protected $stripe;

    private $type = [
        'ALL' => 0, 'CASHIN' => 0, 'CASHOUT' => 0,
    ];

    private $subType = [
        'ALL' => 0, 'COD' => 0, 'BUNDLE' => 0,
    ];

    public function __construct()
    {
        $this->stripe = new Stripe();
    }

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

        if ($operation) {
            $transaction->where('amount', $operation, $amount);
        } else if ($amount) {
            $transaction->whereBetween('amount', [intval($amount), intval($amount) . '.99']);
        }

        $tabs = DB::table('transactions')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('type', DB::raw(
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

    public function withDraw(TransactionRequest $request)
    {
        $merchecntInfo = $this->getMerchentInfo();

        $actualBalance = $merchecntInfo->bundle_balance;
        $paymentMethod = $merchecntInfo->payment_methods;

        if ($actualBalance < $request->amount) {
            return $this->error('The Actual Balance Not Enough', 400);
        }

        $merchecntInfo->bundle_balance = $actualBalance - $request->amount;
        $merchecntInfo->save();

        $payment = collect($paymentMethod)->where('id', $request->payment_method_id)->first();
        if ($payment == null) {
            throw new InternalException('Invalid Payment Method ID');
        }

        $transaction = Transaction::create(
            [
                'type' => 'CASHOUT',
                'merchant_id' => $request->user()->merchant_id,
                'source' => $request->source,
                'created_by' => $request->user()->id,
                'amount' => $request->amount,
                'balance_after' => ($actualBalance - $request->amount),
                'payment_method' => collect($payment),
                'resource' => Request()->header('agent') ?? 'API',
            ]
        );
        // {"id": 1, "code": "zc", "iban": "1231231231", "name": "Zain Cash", "type": "wallet", "name_ar": "زين كاش", "name_en": "Zain Cash", "created_at": "2022-01-04 09:26:56", "provider_code": "zc"}
        WithDrawPayments::dispatch($payment, $request->amount, $transaction);
        return $this->successful('WithDraw Sucessfully');
    }

    public function deposit(TransactionRequest $request)
    {
        $data = $request->validated();
        $merchecntInfo = $this->getMerchentInfo();

        $infoTransaction = [
            'amount' => currency_exchange($data['amount'], $merchecntInfo->currency_code, 'USD'),
            'currency' => 'USD',
            'source' => $data['token'],
            'description' => "Merachnt Deposit " . $merchecntInfo->name,
        ];

        $this->stripe->InvoiceWithToken($infoTransaction);

        Transaction::create(
            [
                'type' => 'CASHOUT',
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
        return $this->error('The COD Balance Is Not Enough', 500);
    }

    public function export(TransactionRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $type = $request->type;
        $date = $request->date;

        $transaction = Transaction::where('merchant_id', $merchentID)
            ->whereDate('created_at', $date)
            ->get();
        if ($transaction->isEmpty()) {
            return $this->response([], 'No Data Retrieved');
        }

        $path = "export/transaction-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = exportXLSX(new TransactionsExport($transaction), $path);
        } else {
            $url = exportPDF('transactions', $path, $transaction);
        }

        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }
}
