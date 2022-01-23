<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;

class AddressesRequests extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function all($keys = null)
    {
        $data = parent::all($keys);
        $data['merchant_id'] = $this->route('merchant_id');
        return $data;
    }

    public function rules()
    {
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'PUT' && strpos($path, 'merchant/{merchant_id}/addresses') !== false) {
            return [
                "name" => "required",
                "county_id" => "required|exists:countries,id",
                "city_id" => "required|exists:cities,id,country_id," . Request::instance()->county_id,
                "area_id" => "required|exists:areas,id,city_id," . Request::instance()->city_id,
                "phone" => "required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14",
                "description" => "required",
            ];

        }

        return [];
    }
}
