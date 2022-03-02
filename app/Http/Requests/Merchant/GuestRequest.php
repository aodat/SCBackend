<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rules\RequiredIf;

class GuestRequest extends FormRequest
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
        if ($this->method() == 'POST' && strpos($path, 'shipments/calculate/fees')) {
            $type = Request::instance()->type;
            $validation = [
                'weight' => 'required|numeric|between:0,9999',
                'type' => 'required|in:express,domestic',
                'is_cod' => 'required|boolean',
            ];
            if ($type == 'express') {
                $validation['country_code'] = 'required';
                if (!empty(Request::instance()->dimention)) {
                    $validation['dimention.length'] = 'required|numeric|between:0,9999';
                    $validation['dimention.height'] = 'required|numeric|between:0,9999';
                    $validation['dimention.width'] = 'required|numeric|between:0,9999';
                }
            } else {
                $validation['city_from'] = 'required';
                $validation['city_to'] = 'required';
            }
            return $validation;
        }
        return [];
    }
}
