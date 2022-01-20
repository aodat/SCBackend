<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpressRatesRequest;
use App\Models\Merchant;

class ExpressRatesController extends Controller
{
    private $merchant;
    public function __construct(ExpressRatesRequest $request)
    {
        $this->merchant = Merchant::findOrFail($request->merchant_id);
    }

    public function index(ExpressRatesRequest $request)
    {
        return $this->response($this->merchant->express_rates, 'Data Retrieved Successfully');
    }

    public function update(ExpressRatesRequest $request)
    {

        $carrier_id = $request->carrier_id;
        $merchant = $this->merchant;
        $express_rates = $merchant->express_rates;

        $rates = collect($express_rates[$carrier_id]['zones']);
        $rate = $rates->where('id', $request->zone_id);

        if ($rate->first() == null) {
            throw new InternalException('Rate id not Exists');
        }
        $current = $rate->keys()->first();

        if ($request->basic) {
            $express_rates[$carrier_id]['zones'][$current]['basic'] = $request->basic;
        }

        if ($request->additional) {
            $express_rates[$carrier_id]['zones'][$current]['additional'] = $request->additional;
        }

        if ($request->max_weight) {
            $express_rates[$carrier_id]['zones'][$current]['max_weight'] = $request->max_weight;
        }

        $merchant->update(['express_rates' => collect($express_rates)]);
    }
}
