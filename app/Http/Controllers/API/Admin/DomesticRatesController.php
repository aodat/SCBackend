<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DomesticRatesRequest;

use App\Models\Merchant;

class DomesticRatesController extends Controller
{
    public function index(DomesticRatesRequest $request)
    {
        $data = $request->validated();
        $merchant = Merchant::findOrFail($data['merchant_id']);
        return $this->response($merchant->domestic_rates, 'Data Retrieved Successfully', 200);
    }

    public function update(DomesticRatesRequest $request)
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        $carrier_id = $data['carrier_id'];
        $merchant_id = $data['merchant_id'];

        unset($data['carrier_id']);
        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $domestic_rates = $merchant->domestic_rates;
        
        $rates = collect($domestic_rates[$carrier_id]);

        // update rate
        $rate = $rates->where('id', $id);
        if ($rate->first() == null)
            throw new InternalException('Rate id not Exists');
        $current = $rate->keys()->first();
        $rate[$current] = $data;
        $rates = $rates->replaceRecursive($rate);

        $domestic_rates[$carrier_id] = $rates;
        
        $merchant->update(['domestic_rates' => $domestic_rates]);
        return $this->successful('Updated Sucessfully');
    }
}
