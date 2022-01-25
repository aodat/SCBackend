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
        if (($this->getMethod() == 'PUT' && strpos($path, 'withdraw') !== false)) {
            return [
                'payment_method_id' => 'required',
            ];
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'deposit') !== false) {
            return [
                'amount' => 'required|numeric',
                "token" => 'required|string',
            ];
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'transfer') !== false) {
            return [
                'amount' => 'required|numeric',
            ];
        } else if ($this->getMethod() == 'POST' && strpos($path, 'export') !== false) {
            return [
                'date' => 'required|date|date_format:Y-m-d',
                'type' => 'in:xlsx,pdf',
            ];
        }

        return [];
    }
}
