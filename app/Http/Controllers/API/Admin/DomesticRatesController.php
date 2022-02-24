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
        $zone_id    = $request->id;
        $price      = $request->price;

        $merchant   = $this->merchant;
        $domestic_rates = $merchant->domestic_rates;


        $new_dom_rates = collect($domestic_rates)->map(function ($data) use ($carrier_id, $zone_id, $price) {
            if ($data['carrier_id'] == $carrier_id) {
                foreach ($data['zones'] as $key => $value) {
                    if ($value['id'] == $zone_id)
                        $data['zones'][$key]['price'] = $price;
                }
            }
            return $data;
        });
        $merchant->update(['domestic_rates' => $new_dom_rates]);
        return $this->successful('Updated Successfully');
    }
}
