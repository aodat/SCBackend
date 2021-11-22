<?php

namespace App\Traits;

use App\Models\Carriers;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

trait SystemConfig
{
    public function domastic()
    {
        $carriers = Carriers::all()->pluck('name', 'id');
        $collection = collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'), true));

        return $collection->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });
    }

    public function express()
    {
        $carriers = Carriers::all()->pluck('name', 'id');
        $collection = collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true));

        return $collection->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });
    }

    public function country()
    {
        return [];
    } 
}
