<?php

namespace App\Http\Requests\Merchant;

use App\Rules\CheckIbanWalletCharRule;
use App\Rules\PincodeVerification;
use App\Rules\ProviderCodeRule;

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
        if (strpos($path, 'payment-methods/create') !== false) {
            return [
                "name" => "required|string",
                "provider_code" => ["required", new ProviderCodeRule()],
                "iban" => ["required", new CheckIbanWalletCharRule(request()->provider_code)],
                "pin_code" => [
                    "required",
                    new PincodeVerification()
                ]
            ];
        }
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
