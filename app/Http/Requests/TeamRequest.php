<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamRequest extends FormRequest
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
        if(strpos($base_url,'team/member/invite') !== false) {
            return [
                'email' => 'required|string|email|max:255|unique:users'
            ];
        }
        return [];
    }
}
