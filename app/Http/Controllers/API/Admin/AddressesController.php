<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddressesRequests;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use Carbon\Carbon;

class AddressesController extends Controller
{
    private $merchant;
    public function __construct(AddressesRequests $request)
    {
        $this->merchant = Merchant::findOrFail($request->merchant_id);

    }

    public function index(AddressesRequests $request)
    {
        return $this->response($this->merchant->addresses, "Data Retrieved Successfully");
    }

    public function update(AddressesRequests $request)
    {
        $merchant = $this->merchant;

        $county_id = $request->county_id;
        $city_id = $request->city_id;
        $area_id = $request->area_id;

        $result = collect($this->merchant->addresses);

        if ($result->where('id', $request->id)->count() == 0) {
            throw new InternalException('Address id not Exists');
        }

        $country = Country::find($county_id);
        $city = City::find($city_id);
        $area = Area::find($area_id);

        if ($result->contains("name", $request->name)) {
            throw new InternalException('name already Exists', 400);
        } else if ($country->code != $merchant->country_code) {
            throw new InternalException('The Country address not same of merchant country', 400);
        }

        $address = $result->where('id', $request->id);
        $current = $address->keys()->first();

        $data = $address->toArray()[$current];
        $result[$current] = [
            'id' => $request->id,
            'name' => $request->name,
            'country_code' => $country->code,
            'country' => $country->name_en,
            'city_code' => $city->code,
            'city' => $city->name_en,
            'area' => $area->name_en,
            'phone' => $request->phone,
            'description' => $request->description,
            'is_default' => $request->is_default ?? false,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $merchant->update(['addresses' => $result]);

        return $this->successful('Created Successfully');
    }
}
