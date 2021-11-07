<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRequest extends FormRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'GET' && strpos($path, 'admin/merchant/{merchant_id}/{type}') !== false) {
            $data['merchant_id'] = $this->route('merchant_id');
            $data['type'] = $this->route('type');
        }
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
        if (strpos($path, 'admin/merchant/update') !== false)
            return [
                'merchant_id' => 'required|exists:merchants,id',
                'type' => 'required|in:individual,corporate',
                'is_active' => 'required|boolean',
            ];
        else if ($this->method() == 'GET' && strpos($path, 'admin/merchant/{merchant_id}/{type}') !== false)
            return [
                'merchant_id' => 'required|exists:merchants,id',
                'type' => 'required|in:documents,addresses,payment_methods,domestic_rates,express_rates'
            ];
        return [];
    }
}
