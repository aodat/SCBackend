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
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use Countries;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseHandler, CarriersManager;
    use SystemConfig, SystemRules;

    public function unauthenticated()
    {
        return $this->error('unauthenticated', 403);
    }

    // public function second()
    // {
    //     DB::transaction(function () {
    //         $tempCity = (json_decode(Storage::disk('local')->get('template/test.json'), true));
    //         $CountryID = (Country::where('code', 'JO')->first()->id);
    //         $cities = City::where('country_id', $CountryID)->pluck('name_en', 'id');
    //         $cities->map(function ($name, $cityID) use ($tempCity) {
    //             $list = $tempCity[$name] ?? [];
    //             if (count($list) > 0) {
    //                 $area = [];
    //                 collect($list)->map(function ($data) use (&$area, $cityID) {
    //                     $area[] = [
    //                         'city_id' => $cityID,
    //                         'name_en' =>  $data['name_english'],
    //                         'name_ar' =>  $data['name_arabic'],
    //                         'created_at' => Carbon::now(),
    //                         'updated_at' => Carbon::now()
    //                     ];
    //                 });
    //                 DB::table('areas')->insert($area);
    //             }
    //         });
    //     });
    // }

    // public function first()
    // {
    //     set_time_limit('0');
    //     DB::transaction(function () {
    //         $ar = Countries::keyValue('ar', 'code', 'label');
    //         $en = Countries::keyValue('en', 'code', 'label');
    //         $payload = [
    //             "ClientInfo" => [
    //                 'UserName' => config('carriers.aramex.USERNAME'),
    //                 'Password' => config('carriers.aramex.PASSWORD'),
    //                 'AccountNumber' => config('carriers.aramex.ACCOUNT_NUMBER'),
    //                 'AccountPin' => config('carriers.aramex.PIN'),
    //                 'AccountEntity' => config('carriers.aramex.ACCOUNT_ENTITY'),
    //                 'AccountCountryCode' => config('carriers.aramex.ACCOUNT_COUNTRY_CODE'),
    //                 'Version' => config('carriers.aramex.VERSION'),
    //                 'Source' => config('carriers.aramex.SOURCE')
    //             ],
    //             "Transaction" => [
    //                 "Reference1" => "",
    //                 "Reference2" => "",
    //                 "Reference3" => "",
    //                 "Reference4" => "",
    //                 "Reference5" => ""
    //             ]
    //         ];

    //         $en->map(function ($data) use ($ar, $payload) {
    //             $payload['CountryCode'] = $data->code;
    //             $t1 = Http::post('https://ws.aramex.net/ShippingAPI.V2/Location/Service_1_0.svc/json/FetchCities', $payload);


    //             $country = Country::create([
    //                 'name_en' => $data->label,
    //                 'name_ar' => $ar->where('code', $data->code)->first()->label,
    //                 'code' => $data->code,

    //             ]);

    //             $CitiesArr = array();
    //             foreach ($t1->json()['Cities'] as $t)
    //                 $CitiesArr[] = [
    //                     'country_id' => $country->id,
    //                     'name_en' => $t,
    //                     'name_ar' => $t
    //                 ];
    //             DB::table('cities')->insert($CitiesArr);
    //         });
    //     });
    // }
}
