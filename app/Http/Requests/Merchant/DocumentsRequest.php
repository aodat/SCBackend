<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class DocumentsRequest extends FormRequest
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
        $path = Request()->path();
        if(strpos($path,'documents/create') !== false) 
            return [
                "type" => "required|in:license,passport,id",
                "url" => "required|url"
            ];
        return [];
    }
}
