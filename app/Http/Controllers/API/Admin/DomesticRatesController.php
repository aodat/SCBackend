<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DomesticRatesRequest;
use App\Models\Merchant;

class DomesticRatesController extends Controller
{
    private $merchant;
    public function __construct(DomesticRatesRequest $request)
    {
        $this->merchant = Merchant::findOrFail($request->merchant_id);
    }

    public function index(DomesticRatesRequest $request)
    {
        return $this->response($this->merchant->domestic_rates, 'Data Retrieved Successfully');
    }

    public function update(DomesticRatesRequest $request)
    {
        $carrier_id = $request->carrier_id;
        $merchant = $this->merchant;
        $domestic_rates = $merchant->domestic_rates;

        $rates = collect($domestic_rates[$carrier_id]);
        $rate = $rates->where('id', $request->id);

        if ($rate->first() == null) {
            throw new InternalException('Rate id not Exists');
        }

        $current = $rate->keys()->first();

        $domestic_rates[$carrier_id][$current]['price'] = $request->price;

        $merchant->update(['domestic_rates' => collect($domestic_rates)]);
        return $this->successful('Updated Successfully');
    }
}
