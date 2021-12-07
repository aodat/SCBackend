<?php

namespace App\Http\Requests;


use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

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

    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'DELETE' && strpos($path, 'team/member/{user_id}') !== false)
            $data['id'] = $this->route('user_id');
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
        if (strpos($path, 'team/member/invite') !== false) {
            return [
                'email' => 'required|string|email|max:255'
            ];
        } else if (strpos($path, 'team/member/{user_id}') !== false) {
            return [
                'id' => [
                    'required',
                    Rule::exists('users')->where(function ($query) {
                        return $query->where('is_owner', false)
                            ->where('merchant_id', Request()->user()->merchant_id);
                    }),
                ]
            ];
        } else if ($this->method() == 'PUT' && strpos($path, 'member') !== false) {
            return [
                'id' => [
                    'required',
                    Rule::exists('users')->where(function ($query) {
                        return $query->where('merchant_id', Request()->user()->merchant_id)->where('status', 'active');
                    }),
                ],
                'scope' => 'required|in:admin,member',
                'role.*' => [
                    Rule::in("payment", "shipping"),
                ],
            ];
        }
        return [];
    }
}
