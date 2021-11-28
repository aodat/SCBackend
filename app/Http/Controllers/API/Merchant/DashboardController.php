<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\DashboardRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class DashboardController extends MerchantController
{
    public function index(DashboardRequest $request)
    {
        $merchant_id =  $request->user()->merchant_id;
        $shipping = array();
        $payment = array();
        $since_at = $request->since_at;
        $until = $request->until;

        $sql_shipping =  DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select(DB::raw("DATE_FORMAT(shp.created_at,'%Y-%m-%d') as date"), "shp.status", DB::raw('sum(amount) as amount'))
            ->groupBy("date", "shp.status")
            ->get();
        $shippingCollect = collect($sql_shipping);
        foreach ($shippingCollect as  $value)
            $shipping[$value->status][$value->date] = $value->amount;

        $payment_sql = Transaction::where('merchant_id',  $merchant_id)
            ->whereBetween('created_at', [$since_at, $until])
            ->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d') as date"), 'type', DB::raw('sum(amount) as amount'))
            ->groupBy('date', 'type')
            ->get();
        $paymentCollect = collect($payment_sql);
        foreach ($paymentCollect as  $value)
            $payment[$value->type][$value->date] = $value->amount;

        $pending_payment =   DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.transaction_id', '=',  null)
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select(DB::raw("DATE_FORMAT(shp.created_at,'%Y-%m-%d') as date"), DB::raw('sum(amount) as amount'))
            ->groupBy('date')
            ->get();
        $pending_payment = collect($pending_payment)->pluck('amount', 'date');
        
        $data = [
            "chart" => [
                "shipping" => [
                    "defts" => $shipping['DRAFT'] ?? 0,
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
                    "drafts" => collect($shipping['DRAFT'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                    "processing" => collect($shipping['PROCESSING'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                    "delivered" => collect($shipping['COMPLETED'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                    "renturnd" => collect($shipping['RENTURND'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                ],
                "payment" => [
                    "outcome" =>  collect($payment['CASHOUT'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                    "income" =>  collect($payment['CASHIN'] ?? 0)->sum(function ($date) {
                        return ($date);
                    }),
                    "pending_payment" => $pending_payment->sum(function ($date) {
                        return ($date);
                    }),
                ]
            ]
        ];

        return $this->response($data, 'Data Retrieved Successfully');
    }
}
