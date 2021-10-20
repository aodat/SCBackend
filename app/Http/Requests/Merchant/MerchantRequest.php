<?php
namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
        $path = Request()->path();
        if(strpos($path,'merchant/profile/update-password') !== false) {
            return [
                'current' => 'required',
                'new' => 'required|confirmed|min:6|max:255',
            ];
        } else if(strpos($path,'merchant/profile/update-profile') !== false) {
            return [
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users,email,'.Auth::id(),
                'phone' => 'required|unique:users,phone,'.Auth::id()
            ];
        } else if(strpos($path,'merchant/verify/phone') !== false) {
            return [
                'phone' => 'required'
            ];
        }
        return [];
    }
}
