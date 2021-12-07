<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;

use App\Rules\Phone;

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
        if (strpos($base_url, 'auth/register') !== false) {
            return [
                'type' => 'in:individual,corporate',
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users',
                'country_code' => 'required|in:JO,KSA',
                'phone' => ['required', 'unique:users', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10', 'max:14', new Phone(Request::instance()->country_code)],
                'password' => 'required|min:6|max:255'
            ];
        } else if (strpos($base_url, 'auth/login') !== false) {
            return [
                'email' => 'email|required',
                'password' => 'required'
            ];
        }
    }
}
