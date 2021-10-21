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
        if(strpos($path,'payment_methods/create') !== false)
            return [
                "name" => "required",
                "iban" => "required",
                "provider" => "required|string"
            ];
        return [];
    }
}
