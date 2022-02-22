<?php

namespace App\Http\Requests\Merchant;

class PaymentLinksRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if (strpos($path, 'payments_link/create') !== false) {
            return [
                "customer_name" => "required|string",
                "customer_email" => "required|email",
                "description" => "required",
                "refference" => "",
                "amount" => 'required|numeric|between:0.0001,9999',
            ];
        } else if (strpos($path, 'payments_link/charge') !== false) {
            return [
                "hash" => "required|exists:payment_links,hash,status,DRAFT",
                "token" => 'required',
            ];
        }
        return [];
    }
}
