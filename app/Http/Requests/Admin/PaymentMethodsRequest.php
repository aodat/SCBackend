<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodsRequest extends FormRequest
{
    public function all($keys = null)
    {
        $data = parent::all($keys);
        $data['merchant_id'] = $this->route('merchant_id');
        return $data;
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'GET' && strpos($path, '{merchant_id}/payment_methods') !== false)
            return [
                'merchant_id' => 'required|exists:merchants,id'
            ];
        else if ($this->getMethod() == 'PUT' && strpos($path, '{merchant_id}/payment_methods') !== false)
            return [
                'merchant_id' => 'required|exists:merchants,id',
                'id' => 'required'
            ];
        return [];
    }
}
