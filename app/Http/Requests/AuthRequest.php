<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
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
        $base_url = Request()->path();
        if(strpos($base_url,'clients/auth/register') !== false) {
            return [
                'type' => 'in:individual,corporate',
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users',
                'phone' => 'required|unique:users',
                'password' => 'required|min:6|max:255|confirmed'
            ];
        } else if(strpos($base_url,'clients/auth/login') !== false)
        {
            return [
                'email' => 'email|required',
                'password' => 'required'
            ];
        }
    }
}