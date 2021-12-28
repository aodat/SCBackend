<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DashboardRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends MerchantController
{
    private $since_at, $until, $merchant_id, $arrayDays;

    public function index(DashboardRequest $request)
    {
        $this->merchant_id =  $request->user()->merchant_id;
        $this->since_at = $request->since_at;
        $this->until = $request->until;
        $this->arrayDays = $this->arrayDays();
        $shipping = $this->shipping();
        $payment = $this->payment();
        $pending_payment = $this->pending_payment();
        $data = [
            "chart" => [
                "shipping" => [
                    "draft" => $shipping['DRAFT'] ?? 0,
                    "proccesing" => $shipping['PROCESSING'] ?? 0,
                    "delivered" => $shipping['COMPLETED'] ?? 0,
                    "renturnd" => $shipping['RENTURND'] ?? 0,
                ],
                "payment" => [
                    "outcome" => $payment['CASHOUT'] ?? 0,
                    "income" => $payment['CASHIN'] ?? 0,
                    "pending_payment" => $pending_payment,
                ]
            ],
            "info" => [
                "shipping" => [
                    "draft" => collect($shipping['DRAFT'] ?? 0)->sum(),
                    "processing" => collect($shipping['PROCESSING'] ?? 0)->sum(),
                    "delivered" => collect($shipping['COMPLETED'] ?? 0)->sum(),
                    "renturnd" => collect($shipping['RENTURND'] ?? 0)->sum(),
                ],
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

    protected function shipping()
    {
        $shipping = [
            "DRAFT" => $this->arrayDays,
            "PROCESSING" => $this->arrayDays,
            "COMPLETED" => $this->arrayDays,
            "RENTURND" => $this->arrayDays,
        ];
        $sql_shipping =  DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('t.merchant_id', '=',  'shp.merchant_id')
            ->where('t.merchant_id', '=',  $this->merchant_id)
            ->whereBetween('shp.created_at', [$this->since_at, $this->until])
            ->select(DB::raw("DATE_FORMAT(shp.created_at,'%Y-%m-%d') as date"), "shp.status", DB::raw('sum(amount) as amount'))
            ->groupBy("date", "shp.status")
            ->get();
        $shippingCollect = collect($sql_shipping);

        foreach ($shippingCollect as  $value)
            $shipping[$value->status][$value->date] = $value->amount;

        return  $shipping;
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
