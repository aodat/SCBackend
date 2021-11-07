<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRequest extends FormRequest
{
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
        return [];
    }
}
