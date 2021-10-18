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
        if(strpos(Request()->path(),'clients/auth/register') !== false)
        {
            return $rules = [
                'type' => 'in:individual,corporate',
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users',
                'phone' => 'required',
                'password' => 'required|min:6|max:255|confirmed',
                'password_confirmation' => 'required|min:6|max:255',
            ];
        } else if(strpos(Request()->path(),'clients/auth/login') !== false) {
            return [
                'email' => 'email|required',
                'password' => 'required'
            ];
        }
    }
}