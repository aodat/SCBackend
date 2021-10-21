<?php

namespace App\Http\Requests\Merchant;

class TransactionRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;
        if (
            ($this->getMethod() == 'PUT' && strpos($path, 'transactions/withdraw') !== false)
        )
            return [
                'amount' => 'required|numeric',
                'payment_method_id' => 'required'
            ];
        return [];
    }
}