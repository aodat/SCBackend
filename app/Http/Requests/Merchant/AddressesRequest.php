<?php

namespace App\Http\Requests\Merchant;

class AddressesRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if(strpos($path,'addresses/create') !== false)
            return [
                "city" => "required",
                "area" => "",
                "phone" => "required",
                "description" => ""
            ];
        return [];
    }
}
