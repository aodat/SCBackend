<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;

use App\Traits\ResponseHandler;
use App\Traits\CarriersManager;
use App\Traits\SystemConfig;
use App\Traits\SystemRules;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseHandler, CarriersManager;
    use SystemConfig, SystemRules;

    public function unauthenticated()
    {
        return $this->error('unauthenticated', 403);
    }

    public function country()
    {
        $data = collect(json_decode(Storage::disk('local')->get('template/city.json')))->pluck('name', 'code');
        return $this->response($data, "Data Retrieved Successfully");
    }

    public function city($code)
    {
        $data = collect(json_decode(Storage::disk('local')->get('template/city.json')))->where('code', strtoupper($code))->first() ?: [];
        return $this->response($data, "Data Retrieved Successfully");
    }
    // Route::get('country/list', [Controller::class, 'country']);
    // Route::get('country/{city_code}/list', [Controller::class, 'city']);

}
