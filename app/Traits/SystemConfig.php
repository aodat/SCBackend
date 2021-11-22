<?php

namespace App\Traits;

use App\Models\Carriers;
use App\Models\Merchant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

trait SystemConfig
{
    public function domastic()
    {
        $carriers = Carriers::all()->pluck('name', 'id');
        $collection = Merchant::find(Auth::user()->merchant_id)->domestic_rates;
        return collect($collection)->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });
    }

    public function express()
    {
        $carriers = Carriers::all()->pluck('name', 'id');
        $collection = Merchant::find(Auth::user()->merchant_id)->express_rates;
        return collect($collection)->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });
    }

    public function countries()
    {
        return [];
    }
}
