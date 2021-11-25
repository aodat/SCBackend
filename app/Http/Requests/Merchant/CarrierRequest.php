<?php

namespace App\Http\Requests\Merchant;

class CarrierRequest extends MerchantRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if (strpos($path, 'carrier/{carrier_id}/update') !== false)
            $data['carrier_id'] = $this->route('carrier_id');

        return $data;
    }
    public function rules()
    {
        $path = Request()->route()->uri;
        if (strpos($path, 'carrier/{carrier_id}/update') !== false)
            return [
                "carrier_id" => "required|exists:carriers,id",
                "is_defult" => "required|boolean",
                "is_enabled" => "required|boolean",
            ];
    }
}
