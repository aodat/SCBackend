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
            return [
                'amount' => 'required|numeric',
                'wallet_number' => 'required|string',
                'pin_code' => 'required',
            ];
        } else if ($this->getMethod() == 'PUT' && strpos($path, 'transfer') !== false) {
            return [
                'amount' => 'required|numeric',
            ];
        } else if ($this->getMethod() == 'POST' && strpos($path, 'export') !== false) {
            return [
                'created_at.since' => 'nullable|date|date_format:Y-m-d',
                'created_at.until' => 'nullable|date|date_format:Y-m-d|after:created_at.since',
                'type' => 'in:xlsx,pdf',
            ];
        }

        return [];
    }
}
