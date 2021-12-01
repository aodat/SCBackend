<?php

namespace App\Http\Requests\Merchant;

use App\Rules\Country;
use App\Rules\City;
use App\Rules\CountryCode;

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
                "country" => ["required"],
                "country_code" => ["required", new CountryCode()],
                "city" => ["required"],
                "city_code" => "required",
                "area" => "required",
                "phone" => "required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14",
                "description" => "required"
            ];
        return [];
    }
}
