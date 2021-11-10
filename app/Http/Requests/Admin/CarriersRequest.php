<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CarriersRequest extends FormRequest
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

    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'PUT' && strpos($path, 'admin/carriers/{carrier_id}') !== false)
            $data['id'] = $this->route('carrier_id');
        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'POST' && strpos($path, 'admin/carriers/create') !== false)
            return [
                'name' => 'required|min:6|max:255|unique:carriers',
                'email' => 'required|email|unique:carriers',
                'phone' => 'required',
                'country_code' => 'required',
                'currency_code' => 'required',
                'is_active' => 'required|boolean'
            ];
        else if ($this->getMethod() == 'PUT' && strpos($path, 'admin/carriers/{carrier_id}') !== false)
            return [
                'id' => 'required|exists:carriers',
                'phone' => 'required',
                'country_code' => 'required',
                'currency_code' => 'required',
                'is_active' => 'required|boolean'
            ];
        return [];
    }
}
