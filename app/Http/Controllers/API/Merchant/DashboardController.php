<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DashboardRequest;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends MerchantController
{
    private $shippingCounter = [
        'DRAFT' => 0,
        'PROCESSING' => 0,
        'COMPLETED' => 0,
        'RENTURND' => 0
    ];

    private $paymentCounter = [
        "CASHOUT" => 0,
        "CASHIN" => 0,
        'PENDING_PAYMENTS' => 0
    ];

    public function index(DashboardRequest $request)
    {
        $merchant_id = $request->user()->merchant_id;
        $shipments = DB::table(DB::raw('shipments s'))
            ->select(DB::raw('date(updated_at) as date'), 'status', DB::raw('count(id) counter'))
            ->where('s.merchant_id', '=', $merchant_id)
            ->groupByRaw('date(updated_at), status')
            ->get();

        $transactions = DB::table(DB::raw('transactions t'))
            ->select(DB::raw('date(updated_at) as date'), 'type as stype', DB::raw('count(id) counter'))
            ->where([['t.merchant_id', $merchant_id], ['t.status', 'COMPLETED']])
            ->groupByRaw('date(updated_at), type')
            ->union(DB::table(DB::raw('shipments s'))
                ->select(DB::raw('date(updated_at) as date'), DB::raw('"PENDING_PAYMENTS" as stype'), DB::raw('count(id) counter'))
                ->where('s.merchant_id', '=', 11)
                ->where('status', '=', 'COMPLETED')
                ->whereNull('transaction_id')
                ->groupByRaw('date(updated_at), status'))
            ->get();


        $shipping_dates = $payment_dates = $shipping = $payments = [];

        $period = CarbonPeriod::create($request->since_at, $request->until);
        foreach ($period as $date) {
            $current = $date->format('Y-m-d');
            $shipping_dates[$current] = [
                'DRAFT' => 0,
                'PROCESSING' => 0,
                'COMPLETED' => 0,
                'RENTURND' => 0
            ];

            $payment_dates[$current] = [
                'CASHIN' => 0,
                'CASHOUT' => 0,
                'PENDING_PAYMENTS' => 0
            ];

            $shipping['DRAFT'][$current] = 0;
            $shipping['PROCESSING'][$current] = 0;
            $shipping['COMPLETED'][$current] = 0;
            $shipping['RENTURND'][$current] = 0;

            $payments['CASHIN'][$current] = 0;
            $payments['CASHOUT'][$current] = 0;
            $payments['PENDING_PAYMENTS'][$current] = 0;
        }

        $shipments->map(function ($shipment) use (&$shipping_dates, &$shipping) {
            $counter = $shipment->counter;
            $date = $shipment->date;
            $status = $shipment->status;

            $shipping_dates[$date][$status] = $counter;
            $shipping[$status][$date] = $counter;
            $this->shippingCounter[$status] += $counter;
        });


        $transactions->map(function ($transaction) use (&$payment_dates, &$payments) {
            $counter = $transaction->counter;
            $date = $transaction->date;
            $status = $transaction->stype;

            $payment_dates[$date][$status] = $counter;
            $payments[$status][$date] = $counter;
            $this->paymentCounter[$status] += $counter;
        });

        $data = [
            "chart" => [
                "shipping" => $shipping,
                "payment" => $payments
            ],
            "info" => [
                "shipping" => $this->shippingCounter,
                "payment" =>  $this->paymentCounter
            ]
        ];

        return $this->response($data, 'Data Retrieved Successfully');
    }
}
