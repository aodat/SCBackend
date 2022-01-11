<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DashboardRequest;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends MerchantController
{
    private $since_at, $until, $merchant_id, $arrayDays;
    private $shippingCounter = [
        'DRAFT' => 0,
        'PROCESSING' => 0,
        'COMPLETED' => 0,
        'RENTURND' => 0,
        'PENDING_PAYMENTS' => 0,
    ];

    public function index(DashboardRequest $request)
    {
        $shipments = DB::table(DB::raw('shipments s'))
            ->select(DB::raw('date(updated_at) as date'), 'status', DB::raw('count(id) counter'))
            ->where('s.merchant_id', '=', $request->user()->merchant_id)
            ->groupByRaw('date(updated_at), status')
            ->get();

        $dates = $shipping = [];

        $period = CarbonPeriod::create($request->since_at, $request->until);
        foreach ($period as $date) {
            $current = $date->format('Y-m-d');
            $dates[$current] = [
                'DRAFT' => 0,
                'PROCESSING' => 0,
                'COMPLETED' => 0,
                'RENTURND' => 0,
                'PENDING_PAYMENTS' => 0
            ];

            $shipping['DRAFT'][$current] = 0;
            $shipping['PROCESSING'][$current] = 0;
            $shipping['COMPLETED'][$current] = 0;
            $shipping['RENTURND'][$current] = 0;
            $shipping['PENDING_PAYMENTS'][$current] = 0;
        }

        $shipments->map(function ($shipment) use (&$dates, &$shipping) {
            $counter = $shipment->counter;
            $date = $shipment->date;

            $status = $shipment->status;
            $dates[$date][$status] = $counter;
            $shipping[$status][$date] = $counter;
            $this->shippingCounter[$status] += $counter;
        });

        $this->merchant_id =  $request->user()->merchant_id;
        $this->until = $request->until;
        $this->arrayDays = $this->arrayDays();


        $payment = $this->payment();
        $pending_payment = $this->pending_payment();
        $data = [
            "chart" => [
                "shipping" => $shipping,
                "payment" => [
                    "outcome" => $payment['CASHOUT'] ?? 0,
                    "income" => $payment['CASHIN'] ?? 0,
                    "pending_payment" => $pending_payment,
                ]
            ],
            "info" => [
                "shipping" => $this->shippingCounter,
                "payment" => [
                    "outcome" =>  collect($payment['CASHOUT'] ?? 0)->sum(),
                    "income" =>  collect($payment['CASHIN'] ?? 0)->sum(),
                    "pending_payment" => $pending_payment->sum()
                ]
            ]
        ];

        return $this->response($data, 'Data Retrieved Successfully');
    }

    protected function arrayDays()
    {
        $start = new Carbon($this->since_at);
        $blackoutDays[] =  $start->addDays(-1);
        $end = new Carbon($this->until);
        $days = $start->diff($end)->days;

        $blackoutDays = array();
        for ($i = 1; $i <= $days; $i++) {
            $date = $start->addDays();
            $blackoutDays[$date->format('Y-m-d')] = 0;
        }

        return collect($blackoutDays)->toArray();
    }

    protected function payment()
    {
        $payment = [
            "CASHOUT" => $this->arrayDays,
            "CASHIN" => $this->arrayDays,
        ];
        $payment_sql = DB::table('transactions')
            ->whereBetween('created_at', [$this->since_at, $this->until])
            ->where('merchant_id', '=',  $this->merchant_id)
            ->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d') as date"), 'type', DB::raw('sum(amount) as amount'))
            ->groupBy('date', 'type')
            ->get();

        $paymentCollect = collect($payment_sql);
        foreach ($paymentCollect as  $value)
            $payment[$value->type][$value->date] = $value->amount;

        return $payment;
    }

    protected function pending_payment()
    {
        $pending_payment  = $this->arrayDays;
        $pendingPaymentSql =   DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('t.merchant_id', '=',  'shp.merchant_id')
            ->where('shp.merchant_id', '=',  $this->merchant_id)
            ->where('shp.transaction_id', '=',  null)
            ->whereBetween('shp.created_at', [$this->since_at, $this->until])
            ->select(DB::raw("DATE_FORMAT(shp.created_at,'%Y-%m-%d') as date"), DB::raw('sum(amount) as amount'))
            ->groupBy('date')
            ->get();
        $pendingPaymentgCollect  = collect($pendingPaymentSql)->pluck('amount', 'date');
        foreach ($pendingPaymentgCollect as  $key => $value)
            $pending_payment[$key] = $value;

        return collect($pending_payment);
    }
}
