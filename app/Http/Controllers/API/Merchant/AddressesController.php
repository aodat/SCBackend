<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Http\Requests\Merchant\AddressesRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class AddressesController extends MerchantController
{
    public function index(AddressesRequest $request)
    {
        $data = $this->getMerchentInfo();
        return $this->response($data->addresses, 'Data Retrieved Successfully', 200);
    }

    public function store(AddressesRequest $request)
    {
        $json = $request->validated();
        $merchant = $this->getMerchentInfo();

        $county_id = $request->county_id;
        $city_id = $request->city_id;
        $area_id = $request->area_id;

        $country = Country::find($county_id);
        $city = City::find($city_id);
        $area = Area::find($area_id);


        $result = collect($merchant->addresses);
        $counter = $result->max('id') ?? 0;

        if ($result->contains("name", $request->name))
            throw new InternalException('name already Exists', 400);
        else if ($country->code != $merchant->country_code)
            throw new InternalException('The Country address not same of merchant country', 400);

        $json = [
            'id' => ++$counter,
            'name' => $request->name,
            'country_code' => $country->code,
            'country' => $country->name_en,
            'city_code' => $city->code,
            'city' => $city->name_en,
            'area' => $area->name_en,
            'phone' => $request->phone,
            'description' => $request->description,
            'is_default' => $request->is_default ?? false,
            'created_at' => Carbon::now()
        ];

        $merchant->update(['addresses' => $result->merge([$json])]);
        return $this->successful('Create Successfully');
    }

    public function delete($id, AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;

        $list = $this->getMerchentInfo();
        $result = collect(Merchant::where('id', $merchantID)->select('addresses')->first()->addresses);

        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['addresses' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
