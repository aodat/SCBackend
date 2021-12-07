<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
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
        $data = parent::all($keys);
        $path = Request()->route()->uri;

        $data['merchant_id'] = $this->route('merchant_id');
        if ($this->getMethod() == 'PUT' && strpos($path, '{merchant_id}/document/{id}') !== false)
            $data['id'] = $this->route('id');
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

        if ($this->getMethod() == 'POST' && strpos($path, 'merchant/{merchant_id}/document') !== false)
            return [
                "type" => "required|in:license,passport,id",
                "file" => "required|mimes:pdf,jpg,jpeg"
            ];
        else if ($this->getMethod() == 'PUT' && strpos($path, '{merchant_id}/document/{id}') !== false)
            return [
                "status" => "required|in:verified,rejected"
            ];
        return [];
    }
}
