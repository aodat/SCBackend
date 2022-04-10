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
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'deposit/request') !== false) {
            return [
                'amount' => 'required|numeric',
                'wallet_number' => 'required|string',
            ];
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'deposit') !== false) {
            $validation = [
                'amount' => 'required|numeric',
                'type' => 'required|in:wallet,stripe',
            ];

            if (request()->type == 'stripe')
                $validation['token'] = 'required';
            else
                $validation['wallet_number'] = 'required';


            return $validation;
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'transfer') !== false) {
            return [
                'amount' => 'required|numeric',
            ];
        } else if ($this->getMethod() == 'POST' && strpos($path, 'export') !== false) {
            return [
                'created_at.since' => 'nullable|date',
                'created_at.until' => 'nullable|date',
                'type' => 'in:CASHIN,CASHOUT,*',
                'subtype' => 'in:COD,BUNDLE',
                'format' => 'in:xlsx,pdf'
            ];
        }

        return [];
    }
}
