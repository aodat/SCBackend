<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DashboardRequest;
use Illuminate\Support\Facades\DB;

class DashboardController extends MerchantController
{
    private $shippinInfoCard = [
        'DRAFT' => ['counter' => 0, 'rate' => 0],
        'PROCESSING' => ['counter' => 0, 'rate' => 0],
        'COMPLETED' => ['counter' => 0, 'rate' => 0],
        'RENTURND' => ['counter' => 0, 'rate' => 0],
    ];

    private $overall = [
        'DRAFT' => 0,
        'PROCESSING' => 0,
        'COMPLETED' => 0,
        'RENTURND' => 0,
        "CASHOUT" => 0,
        "CASHIN" => 0,
        'PENDING_PAYMENTS' => 0,
    ];

    private $shippingChart = [
        'DRAFT' => null,
        'PROCESSING' => null,
        'COMPLETED' => null,
        'RENTURND' => null,
    ];

    private $paymentChart = [
        'CASHIN' => null,
        'CASHOUT' => null,
        'PENDING_PAYMENTS' => null,
    ];

    private $paymentInfoCard = [
        'CASHIN' => ['counter' => 0, 'amount' => 0],
        'CASHOUT' => ['counter' => 0, 'amount' => 0],
        'PENDING_PAYMENTS' => ['counter' => 0, 'amount' => 0],
    ];

    public function index(DashboardRequest $request)
    {
        $merchant_id = $request->user()->merchant_id;

        $shipments = DB::table(DB::raw('shipments s'))
            ->where('s.merchant_id', '=', $merchant_id)
            ->where('s.is_deleted', false);

        $shippingOverAll = $shipments->select('status', DB::raw('count(id) counter'))
            ->groupByRaw('status')
            ->pluck('counter', 'status')
            ->toArray();

        $shipping = $shipments->select(DB::raw('date(updated_at) as date'), 'status', DB::raw('count(id) counter'))
            ->whereBetween('s.updated_at', [$request->since_at, $request->until])
            ->groupByRaw('date(updated_at), status')
            ->get();

        $totalData = $shipping->sum('counter');
        $shipping->map(function ($shipment) {
            $status = $shipment->status;
            $date = $shipment->date;

            $this->shippingChart[$status][$date] = $shipment->counter;
            $this->shippinInfoCard[$status]['counter'] += $shipment->counter;
        });

        if ($totalData > 0) {
            $this->shippinInfoCard = array_map(function ($data) use ($totalData) {
                return [
                    'counter' => $data['counter'],
                    'rate' => ($data['counter'] > 0 ? round(($data['counter'] / $totalData) * 100) : 0) . '%',
                ];

            }, $this->shippinInfoCard);
        }

        $transactions = DB::table(DB::raw('transactions t'))
            ->select(DB::raw('date(updated_at) as date'), 'type as stype', DB::raw('count(id) counter'), DB::raw('sum(amount) as total'))
            ->where([['t.merchant_id', $merchant_id], ['t.status', 'COMPLETED']])
            ->where('t.subtype', 'COD')
            ->groupByRaw('date(updated_at), type');

        $pendingPayments = DB::table(DB::raw('shipments s'))
            ->select(
                DB::raw('date(updated_at) as date'),
                DB::raw('"PENDING_PAYMENTS" as stype'),
                DB::raw('count(id) counter'),
                DB::raw('sum(cod) as total')
            )
            ->where('s.merchant_id', '=', $merchant_id)
            ->where('status', '=', 'COMPLETED')
            ->where('s.is_deleted', false)
            ->whereNull('transaction_id')
            ->groupByRaw('date(updated_at), status');

        $transactionOverAll = $transactions->union(
            $pendingPayments
        );

        $transactionByDates = $transactions->union(
            $pendingPayments
        )->get()->whereBetween('date', [$request->since_at, $request->until]);

        $this->overall = array_merge($this->overall, $shippingOverAll, $transactionOverAll->pluck('total', 'stype')->toArray());

        $transactionByDates->map(function ($transaction) {
            $type = $transaction->stype;
            $date = $transaction->date;
            $counter = $transaction->counter;
            $total = $transaction->total;

            $this->paymentChart[$type][$date] = $counter;
            $this->paymentInfoCard[$type] = [
                'counter' => $counter,
                'amount' => $total,
            ];
        });

        $data = [
            'overall' => $this->overall,
            'shipping' => [
                'cards' => $this->shippinInfoCard,
                'chart' => $this->shippingChart,
            ],
            'payments' => [
                'cards' => $this->paymentInfoCard,
                'chart' => $this->paymentChart,
            ],
        ];
        return $this->response($data, 'Data Retrieved Successfully');
    }
}
