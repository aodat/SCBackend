<?php

namespace App\Traits;

use App\Models\Carriers;
use App\Models\Merchant;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

trait SystemConfig
{
    public function CarrierMerchant($select = null)
    {

        $merchant =   Merchant::find(Auth::user()->merchant_id);

        $result =   collect($merchant->carriers);
        $Carriers =  Carriers::all();

        $collection = collect($Carriers)->map(function ($data) use ($result, $select) {
            $carrier = $result->where("carrier_id", $data->id)->first();
            if ($carrier === null) {
                $carrier = [
                    'carrier_id' => (int) $data->id,
                    'name' => $data->name,
                    'is_defult' => false,
                    'is_enabled' => true,
                    'create_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            } else
                $carrier['name'] = $data->name;

            return $carrier;
        });

        return $collection;
    }
    public function domastic()
    {
        $carriers = collect(Carriers::all()->pluck('name', 'id'));
        $domestic_rates = Merchant::find(Auth::user()->merchant_id)->domestic_rates;

        $Merchantcarrier = $this->CarrierMerchant();
        $is_enabled = collect($Merchantcarrier->where('is_enabled', true))->pluck('name');
        $domestic_rates = collect($domestic_rates)->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });
        $diffCarriers=  $carriers->diff($is_enabled->toArray());
        $domestic_rates = $domestic_rates->diffKeys($diffCarriers->flip()->toArray());
        return $domestic_rates;
    }

    public function express()
    {
        $carriers = Carriers::all()->pluck('name', 'id');
        $collection = Merchant::find(Auth::user()->merchant_id)->express_rates;
        $Merchantcarrier = $this->CarrierMerchant();
        $domestic_rates =  collect($collection)->keyBy(function ($value, $key) use ($carriers) {
            return $carriers[$key];
        });

        $is_enabled = collect($Merchantcarrier->where('is_enabled', true))->pluck('name');
        $diffCarriers =  $carriers->diff($is_enabled->toArray());
        $domestic_rates = $domestic_rates->diffKeys($diffCarriers->flip()->toArray());
        return $domestic_rates;
    }

    public function countries()
    {
        return [];
    }
}
