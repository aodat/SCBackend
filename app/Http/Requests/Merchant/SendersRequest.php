<?php

namespace App\Http\Requests\Merchant;

class SendersRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if(strpos($path,'senders/create') !== false)
            return [
                "name" => "required",
                "phone" => "required"
            ];
        return [];
    }
}
