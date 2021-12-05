<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Support\Facades\Request;

class AddressesRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if (strpos($path, 'addresses/create') !== false)
            return [
                "name" => "required",
                "county_id" => "required|exists:countries,id",
                "city_id" => "required|exists:cities,id,country_id,".Request::instance()->county_id,
                "area_id" => "required|exists:areas,id,city_id,".Request::instance()->city_id,
                "phone" => "required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14",
                "description" => "required"
            ];
        return [];
    }
}
