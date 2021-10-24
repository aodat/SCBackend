<?php

namespace App\Http\Requests\Merchant;

class PickuptRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();
        if(strpos($path,'pickups/create') !== false)
            return [
                'carrier_id' => 'required|exists:carriers,id',
                "from" => "required|date|date_format:Y-m-d",
                "to" => "required|date|after:from|date_format:Y-m-d"
            ];
        return [];
    }
}