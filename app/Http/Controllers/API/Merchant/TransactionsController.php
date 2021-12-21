<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exports\TransactionsExport;
use App\Http\Requests\Merchant\TransactionRequest;
use App\Models\Invoices;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Libs\Stripe;

class TransactionsController extends MerchantController
{

    protected $stripe;

    private $type = [
        'ALL' => 0, 'CASHIN' => 0, 'CASHOUT' => 0
    ];

    public function __construct()
    {
        $this->stripe = new Stripe();
    }

    public function index(TransactionRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subDays(3)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $types = $filters['types'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $sources = $filters['sources'] ?? [];
        $amount = $filters['amount']['val'] ?? null;
        $operation = $filters['amount']['operation'] ?? null;
        $paginated = array();


        $transaction = Transaction::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($statuses))
            $transaction->whereIn('status', $statuses);

        if (count($sources))
            $transaction->whereIn('source', $sources);

        if (count($types))
            $transaction->whereIn('type', $types);

        if ($operation)
            $transaction->where('amount', $operation, $amount);
        else if ($amount)
            $transaction->whereBetween('amount', [intval($amount), intval($amount) . '.99']);

        $tabs = DB::table('shipments')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('type', DB::raw(
                'count(type) as counter'
            ))
            ->groupBy('type')
            ->pluck('counter', 'type');

        $tabs = collect($this->type)->merge(collect($tabs));
        $tabs['ALL'] = $tabs['CASHIN'] + $tabs['CASHOUT'];

        return $this->pagination($transaction->paginate(request()->per_page ?? 10), ['tabs' => $tabs]);
    }

    public function show($id, TransactionRequest $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function withDraw(TransactionRequest $request)
    {
        $merchecntInfo = $this->getMerchentInfo();

        $actualBalance = $merchecntInfo->actual_balance;
        $paymentMethod = $merchecntInfo->payment_methods;
        $paymentMethodID = $request->payment_method_id;

        if ($actualBalance < $request->amount)
            return $this->error('The Actual Balance Not Enough', 400);


        $merchecntInfo->actual_balance = $actualBalance - $request->amount;
        $merchecntInfo->save();


        $selectedPayment = collect($paymentMethod)->reject(function ($value) use ($paymentMethodID) {
            if ($value['id'] != $paymentMethodID)
                return $value;
        });
        $selectedPayment = array_values($selectedPayment->toArray());

        Transaction::create(
            [
                'type' => 'CASHOUT',
                'merchant_id' => $request->user()->merchant_id,
                'source' => $request->source,
                'created_by' => $request->user()->id,
                'balance_after' => $request->amount,
                'payment_method' => collect($selectedPayment),
                'resource' => Request()->header('agent') ?? 'API'
            ]
        );
    }

    public function deposit(TransactionRequest $request)
    {
        $data = $request->validated();
        $merchecntInfo = $this->getMerchentInfo();

        $infoTransaction =   [
            'amount' =>  $data['amount'],
            'currency' => 'USD', // $merchecntInfo->currency_code,
            'source' => $data['token'],
            'description' => "Merachnt Deposit " . $merchecntInfo->name,
        ];

        $this->stripe->InvoiceWithToken($infoTransaction);

        unset($data['currency'], $data['token'], $data['description']);
        $data['customer_name'] = $merchecntInfo->name;
        $data['customer_email'] = $merchecntInfo->email;
        $data['fk_id'] = null;
        $data['merchant_id'] = $request->user()->merchant_id;
        $data['user_id'] = $request->user()->id;
        $data['resource'] = 'WEB';
        Invoices::create($data);

        return $this->successful('Deposit Sucessfully');
    }

    public function export(TransactionRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $type = $request->type;
        $date = $request->date;

        $transaction = Transaction::where('merchant_id', $merchentID)
            ->whereDate('created_at', $date)
            ->get();
        if ($transaction->isEmpty())
            return $this->response([], 'No Data Retrieved');

        $path = "export/transaction-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx')
            $url = exportXLSX(new TransactionsExport($transaction), $path);
        else
            $url = exportPDF('transactions', $path, $transaction);

        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }
}
