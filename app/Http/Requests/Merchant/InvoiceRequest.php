<?php

namespace App\Http\Requests\Merchant;

class InvoiceRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if(strpos($path,'invoice/create') !== false) 
            return [
                "customer_name" => "required|string",
                "customer_email" => "required|email",
                "description" => "required",
                "amount" => 'required|numeric|between:0.0001,9999'
            ];
        return [];
    }
}