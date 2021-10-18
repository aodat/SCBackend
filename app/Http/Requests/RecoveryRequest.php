<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class RecoveryRequest extends FormRequest
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
        $baseName = Request()->path();
        if(strpos(Request()->path(),'clients/auth/forgetpassword') !== false)
        {
            return [
                'email' => 'required|string|email|max:255|exists:users'
            ];
        } else if(strpos(Request()->path(),'clients/password/reset') !== false) {
            return [
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|confirmed|min:8',
            ];
        }
    }
}
