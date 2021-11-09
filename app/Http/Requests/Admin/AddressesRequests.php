<?php

namespace App\Http\Requests\Admin;

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
                'city' => 'required|string',
                'name' => 'required|string',
                'city_code' => 'required|string',
                'country' => 'required|string',
                'country_code' => 'required|string',
                'area' => 'required|string',
                'phone' => 'required',
                'description' => 'required',
            ];

        return [];
    }
}
