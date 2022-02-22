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
        $shipments = DB::table(DB::raw('shipments s'))
            ->select('id', 'merchant_id', 'awb', 'fees', 'consignee_city', 'cod', 'chargable_weight', 'transaction_id', 'status')
            ->where('group', 'DOM')
            ->where('status', '<>', 'DRAFT')
            // ->whereBetween(DB::raw('date(created_at)'), ['2022-02-12', '2022-02-25'])
            ->orderBy('merchant_id')
            ->get();
        $counter = 1;
        echo "<table border='1'></tr>";
        $shipments->map(function ($shipment) use (&$counter) {
            $newFees = (new ShipmentController)->calculateDomesticFees(
                1,
                $shipment->consignee_city,
                $shipment->chargable_weight,
                $shipment->merchant_id
            );

            if ($newFees != $shipment->fees) {
                echo "<tr>
                        <td>{$shipment->id}</td>
                        <td>{$shipment->merchant_id}</td>
                        <td>{$shipment->awb}</td>
                        <td>{$shipment->transaction_id}</td>
                        <td>{$shipment->fees}</td>
                        <td>{$newFees}</td>
                        <td>{$shipment->status}</td>
                    </tr>
                ";

                $counter++;
            }
        });
        echo "<table>";
        echo "<hr>";
        echo "Result $counter";
    }
}
