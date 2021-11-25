<?php

namespace App\Traits;

use App\Models\Carriers;

use Illuminate\Support\Facades\App;

trait SystemConfig
{
    public function domastic()
    {
       return $enabledCarriers = Carriers::get()->where('is_enabled', 1);
        if ($enabledCarriers == null)
            return [];
        $enabledCarriers = $enabledCarriers->pluck('name', 'id');
        $domestic_rates = App::make('merchantInfo')->domestic_rates;
        $domestic_rates = collect($domestic_rates)->reject(function ($value, $key) use ($enabledCarriers) {
            return !(isset($enabledCarriers[$key]));
        })->keyBy(function ($value, $key) use ($enabledCarriers) {
            return $enabledCarriers[$key];
        });
        return $domestic_rates;
    }

    public function express()
    {

        $enabledCarriers = Carriers::get()->where('is_enabled', 1);
        if ($enabledCarriers == null)
            return [];
        $enabledCarriers = $enabledCarriers->pluck('name', 'id');
        $express_rates = App::make('merchantInfo')->express_rates;
        $express_rates = collect($express_rates)->reject(function ($value, $key) use ($enabledCarriers) {
            return !(isset($enabledCarriers[$key]));
        })->keyBy(function ($value, $key) use ($enabledCarriers) {
            return $enabledCarriers[$key];
        });
        return $express_rates;
    }

    public function countries()
    {
        return [];
    }
}
