<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use App\Traits\ResponseHandler;
use App\Traits\CarriersManager;
use App\Traits\SystemConfig;
use App\Traits\SystemRules;
use Illuminate\Support\Facades\Storage;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseHandler, CarriersManager;
    use SystemConfig, SystemRules;

    public function unauthenticated()
    {
        return $this->error('unauthenticated', 403);
    }

    public function json()
    {
        $lists = collect(json_decode(Storage::disk('local')->get('template/rates/jo.json'), true));
        $data = [];
        $lists->map(function ($list) use (&$data) {
            foreach ($list as $key => $value) {
                if ($value != 0) {
                    $data[$list['country_code']][] = [
                        'carrier_id' => $key,
                        'zone_id' => $value
                    ];
                }
            }
        });
        echo json_encode($data);
        die;
    }
}
