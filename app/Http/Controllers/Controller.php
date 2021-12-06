<?php

namespace App\Http\Controllers;

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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    function test()
    {
        

        $tempCity = (json_decode(Storage::disk('local')->get('template/areas/ksa.json'), true));
        $CountryID = (Country::where('code', 'SA')->first()->id);
        $cities = City::where('country_id', $CountryID)->pluck('name_en', 'id');
        $cities->map(function ($name, $cityID) use ($tempCity) {
            $list = $tempCity[$name] ?? [];
            if (count($list) > 0) {
                $area = [];
                collect($list)->map(function ($data) use (&$area, $cityID) {
                    $area[] = [
                        'city_id' => $cityID,
                        'name_en' =>  $data['name_english'],
                        'name_ar' =>  $data['name_arabic'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                });
                DB::table('areas')->insert($area);
            }
        });
    }
}
