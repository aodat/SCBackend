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
                'payment_method_id' => 'required',
                'source' => 'required|in:shipment,creditcard,invoice,order'
            ];
        else if (
            $this->getMethod() == 'PUT' && strpos($path, 'transactions/deposit') !== false
        )
            return [
                'amount' => 'required|numeric',
                "token" => 'required|string',
            ];

        else if (
            $this->getMethod() == 'POST' && strpos($path, 'transactions/export') !== false
        )
            return [
                'date' => 'required|date|date_format:Y-m-d',
                'type' => 'in:xlsx,pdf'
            ];
        return [];
    }
}
