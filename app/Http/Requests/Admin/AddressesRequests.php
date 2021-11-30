<?php

namespace App\Http\Requests\Admin;

use App\Rules\city;
use App\Rules\CountryCode;
use App\Rules\country;
use Illuminate\Foundation\Http\FormRequest;

class AddressesRequests extends FormRequest
{
    function all($keys = null)
    {
        $data = parent::all($keys);
        $data['merchant_id'] = $this->route('merchant_id');
        return $data;
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'PUT' && strpos($path, 'merchant/{merchant_id}/addresses') !== false)
            return [
                'merchant_id' => 'required|exists:merchants,id',
                'country' => ['required', new country()],
                'country_code' => ['required', new CountryCode()],
                'city' => ['required', new city()],
                'name' => 'required|string',
                'city_code' => 'required|string',
                'area' => 'required|string',
                'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14',
                'description' => 'required',
            ];

        return [];
    }
}
