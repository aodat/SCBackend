<?php

namespace App\Http\Requests\Merchant;

class PaymentMethodsRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if (strpos($path, 'payment-methods/create') !== false)
            return [
                "name" => "required|string",
                "provider_code" => "required|string",
                "iban" => "required",
                "pin_code" => "required"
            ];
        return [];
    }
}
