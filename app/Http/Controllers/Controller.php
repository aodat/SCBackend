<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Traits\CarriersManager;
use App\Traits\ResponseHandler;
use App\Traits\SystemRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseHandler, CarriersManager;
    use SystemRules;

    public function unauthenticated()
    {
        return $this->error('unauthenticated', 403);
    }

    public function json()
    {
        set_time_limit(0);
        DB::transaction(function () {
            $merchantsTransaction = DB::table(DB::raw('transactions t'))
                ->distinct()
                ->select('merchant_id')
                ->where('subtype', '=', 'COD')
                ->orderBy('merchant_id', 'ASC')
                ->get();

            $merchantsTransaction->map(function ($trans) {
                $transactions = DB::table(DB::raw('transactions t'))
                    ->where('subtype', '=', 'COD')
                    ->where('merchant_id', '=', $trans->merchant_id)
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->toArray();
                $balance_after = 0;
                echo "<h1> Mercahnt ID : " . $trans->merchant_id . "</h1>";
                foreach ($transactions as $key => $value) {
                    if ($value->type == 'CASHIN')
                        $balance_after += $value->amount;
                    else
                        $balance_after -= $value->amount;


                    echo  " Balance : " . $value->amount . " Balance After : " . $balance_after;
                    if ($value->type == 'CASHOUT')
                        echo "<hr>";
                    echo "<br>";

                    // DB::table('transactions')
                    //     ->where('id', $value->id)
                    //     ->update(
                    //         [
                    //             'balance_after' => $balance_after
                    //         ]
                    //     );
                }
                echo "<hr>";
            });
        });

        echo "Done";
    }
}
