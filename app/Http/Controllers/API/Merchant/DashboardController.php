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

        $sql =  DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->whereBetween('t.created_at', [$request->since_at, $request->until])
            ->select('shp.status', DB::raw('sum(amount) as amount'))
            ->groupBy('shp.status')
            ->get();

        $shiping = collect($sql)->pluck('amount', 'status');


        $sql2 = Transaction::where('merchant_id',  $merchant_id)
            ->whereBetween('created_at', [$request->since_at, $request->until])
            ->select('type', DB::raw('sum(amount) as amount'))
            ->groupBy('type')
            ->get();
        $payment = collect($sql2)->pluck('amount', 'type');

        $pending_payment =   DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.transaction_id', '=',  null)
            ->whereBetween('t.created_at', [$request->since_at, $request->until])
            ->select(DB::raw('sum(amount) as amount'))
            ->toSql();
        // $pending_payment = collect($pending_payment);
        
        // $data = [
        //     "shiping" => [
        //         "defts" => $shiping['DRAFT'] ?? 0,
        //         "proccesing" => $shiping['PROCESSING'] ?? 0,
        //         "delivered" => $shiping['COMPLETED'] ?? 0,
        //         "renturnd" => $shiping['RENTURND'] ?? 0,
        //     ],

        //     "payment" => [
        //         "Outcome" => $payment['CASHOUT'] ?? 0,
        //         "income" => $payment['CASHIN'] ?? 0,
        //         "pending_payment" => $pending_payment['amount'] ?? 0,

        //     ]
        // ];

        return $this->response($pending_payment, 'Retrieved Successfully');
    }
}
