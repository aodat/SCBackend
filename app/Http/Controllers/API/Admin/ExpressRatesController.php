<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;

use App\Models\Merchant;
use App\Http\Requests\Admin\ExpressRatesRequest;

use App\Exceptions\InternalException;
class ExpressRatesController extends Controller
{
    
    public function index(ExpressRatesRequest $request)
    {
        $data = $request->validated();
        $merchant = Merchant::findOrFail($data['merchant_id']);
        return $this->response($merchant->express_rates, 'Data Retrieved Successfully', 200);
    }

    public function update(ExpressRatesRequest $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? null;
        $carrier_id = $data['carrier_id'];
        $merchant_id = $data['merchant_id'];

        unset($data['carrier_id']);
        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $express_rates = $merchant->express_rates;
        
        $rates = collect($express_rates[$carrier_id]['zones']);

        // update rate
        $rate = $rates->where('id', $id);
        if ($rate->first() == null)
            throw new InternalException('Rate id not Exists');
        $current = $rate->keys()->first();
        $rate[$current] = $data;
        $rates = $rates->replaceRecursive($rate);

        $express_rates[$carrier_id]['zones'] = $rates;
        
        $merchant->update(['express_rates' => $express_rates]);
        return $this->successful('Updated Successfully');
    }
}
