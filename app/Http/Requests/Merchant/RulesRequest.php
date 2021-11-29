<?php

namespace App\Http\Requests\Merchant;

class RulesRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if (strpos($path, 'rules/create') !== false)
            return [
                'name' => 'required',
                'rules.*.type' => 'required|in:express,dom',
                'rules.*.sub-type' => 'required|in:cod,weight,city',
                'rules.*.constraint' => 'required',
                'rules.*.value' => 'required',
                'action' => 'required|array'
            ];
        return [];
    }
}
