<?php

namespace App\Http\Requests;

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

    public function all($keys = null)
    {
        $url = Request()->route()->uri;
        $method = $this->getMethod();
        $data = parent::all($keys);
        if (
                ($method == 'GET' && strpos($url, 'merchant/{col}') !== false) ||
                ($method == 'POST' && strpos($url, 'merchant/{col}/create') !== false) ||
                ($method == 'DELETE' && strpos($url, 'merchant/{col}/{id}') !== false)
            ) {
            $data['col'] = $this->route('col');
        }

        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $base_url = Request()->route()->uri;
        $method = $this->getMethod();

        if(strpos($base_url,'merchant/profile/update-password') !== false) {
            return [
                'current' => 'required',
                'new' => 'required|confirmed|min:6|max:255',
            ];
        } else if(strpos($base_url,'merchant/profile/update-profile') !== false) {
            return [
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users,email,'.Auth::id(),
                'phone' => 'required|unique:users,phone,'.Auth::id()
            ];
        } else if($method == 'GET' && strpos($base_url,'merchant/{col}') !== false) {
            return [
                "col" => "in:payment_methods,documents,addresses,senders",
            ];
        } else if($method == 'POST' && strpos($base_url,'merchant/{col}/create') !== false) {
            $path = Request()->path();
            if(strpos($path,'payment_methods/create') !== false) {
                return [
                    "col" => "required|in:payment_methods",
                    "name" => "required",
                    "iban" => "required",
                    "provider" => "required|string"
                ];
            } else if(strpos($path,'documents/create') !== false) {
                return [
                    "col" => "required|in:documents",
                    "type" => "required|in:license,passport,id",
                    "url" => "required|url"
                ];
            } else if(strpos($path,'addresses/create') !== false) {
                return [
                    "col" => "required|in:addresses",
                    "city" => "required",
                    "area" => "",
                    "phone" => "required",
                    "description" => ""
                ];
            } else if(strpos($path,'senders/create') !== false) {
                return [
                    "col" => "required|in:senders",
                    "name" => "required",
                    "phone" => "required"
                ];
            }
        } else if($method == 'DELETE' && strpos($base_url,'merchant/{col}/{id}') !== false) {
            return [
                "col" => "in:payment_methods,documents,addresses,senders"
            ];
        }
        return [];
    }
}
