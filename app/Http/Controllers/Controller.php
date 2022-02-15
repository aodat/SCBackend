<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Traits\CarriersManager;
use App\Traits\ResponseHandler;
use App\Traits\SystemRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

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
        die('Stop Work');
        set_time_limit(0);
        $shipments = Shipment::where('status', 'COMPLETED')->where('is_collected', false)->whereNotNull('delivered_at')->get();
        $shipments->map(function ($shipment) {
            $json = [
                "UpdateCode" => "SH239",
                "WaybillNumber" => $shipment->external_awb,
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.shipcash.net/api/aramex-webhook',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($json),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                ),
            ));

            echo $response = curl_exec($curl);
            echo "<br>";

            curl_close($curl);

            return $shipment;
        });
    }
}
