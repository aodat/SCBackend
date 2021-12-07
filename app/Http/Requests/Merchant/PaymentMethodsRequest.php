<?php

namespace App\Http\Requests\Merchant;

use App\Rules\PincodeVerification;

class PaymentMethodsRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;
        if (strpos($path, 'payment-methods/create') !== false)
            return [
                "name" => "required|string",
                "provider_code" => "required|string",
                "iban" => "required",
                "pin_code" => [
                    "required",
                    new PincodeVerification()
                ]
            ];
        if (strpos($path, 'payment-methods/{id}') !== false)
            return [
                "pin_code" => [
                    "required",
                    new PincodeVerification()
                ]
            ];
        return [];
    }
}
