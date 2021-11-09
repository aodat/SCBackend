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
        $data['merchant_id'] = $this->route('merchant_id');
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
                "url" => "required|file",
                "type"      => "required|string",
                "verified"      => "required|string",
                "rejected"      => "required|string",
                "verified_at"      => "required|string",
            ];
        return [];
    }
}
