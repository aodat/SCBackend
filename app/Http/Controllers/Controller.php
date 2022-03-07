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
        die;
        DB::transaction(function () {
            $lists = DB::table(DB::raw('transactions t'))
                ->select('merchant_id', DB::raw('(
                    select cod_balance 
                    from merchants m 
                    where m.id = merchant_id 
                ) cod_balance'), DB::raw('sum(amount) as total'))
                ->whereRaw('id NOT IN (
                    select transaction_id 
                    from shipments s 
                    where transaction_id is not null 
                )')
                ->where('type', '=', 'CASHIN')
                ->where('subtype', '=', 'COD')
                ->where('description', '=', 'Aramex SH239 Tracking')
                ->groupBy('merchant_id')
                ->orderByRaw('merchant_id ASC,total DESC')
                ->get();

            $lists->map(function ($list) {
                $merchant = Merchant::findOrFail($list->merchant_id);
                $merchant->cod_balance = $merchant->cod_balance - $list->total;
                $merchant->save();
            });
        });

        echo "Done";
    }
}
