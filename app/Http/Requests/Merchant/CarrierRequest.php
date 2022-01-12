<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Support\Facades\Request;

class CarrierRequest extends MerchantRequest
{
    private $carriers = [
        1 => [
            'env.aramex_account_country_code' => 'required',
            'env.aramex_account_entity' => 'required',
            'env.aramex_account_number' => 'required',
            'env.aramex_password' => 'required',
            'env.aramex_pin' => 'required',
            'env.aramex_username' => 'required',
            'env.aramex_version' => 'required',
            'env.aramex_source' => 'required',
        ],
        2 => [
            'env.dhl_account_number' => 'required',
            'env.dhl_password' => 'required',
            'env.dhl_site_id' => 'required'
        ],
        3 => [
            'env.fedex_account_number' => 'required',
            'env.fedex_meter_number' => 'required',
            'env.fedex_key' => 'required',
            'env.fedex_password' => 'required'
        ]
    ];

    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if (strpos($path, 'carrier/{carrier_id}/update') !== false || strpos($path, 'carrier/{carrier_id}/env') !== false )
            $data['carrier_id'] = $this->route('carrier_id');

        return $data;
    }
    public function rules()
    {
        $path = Request()->route()->uri;
        if (strpos($path, 'carrier/{carrier_id}/update') !== false) {
            $validation = [
                "carrier_id" => "required|exists:carriers,id",
                "is_defult" => "boolean",
                "is_enabled" => "boolean",
            ];

            if (!empty(Request::instance()->env))
                $validation = array_merge($validation, $this->carriers[Request::instance()->carrier_id]);

            return $validation;
        } else if (strpos($path, 'carrier/{carrier_id}/env') !== false)
            return [
                "carrier_id" => "required|exists:carriers,id",
            ];
        return [];
    }
}
