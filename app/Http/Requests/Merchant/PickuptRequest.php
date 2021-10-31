<?php

namespace App\Http\Requests\Merchant;

use Carbon\Carbon;

class PickuptRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $new_date = Carbon::parse(Carbon::today())->addDays(3);
        $new_date->toDateString();

        $path = Request()->path();
        if(strpos($path,'pickups/create') !== false)
            return [
                'address_id' => 'required',
                'carrier_id' => 'required|exists:carriers,id',
                "pickup_date" => "required|date|date_format:Y-m-d|after:today|before:$new_date"
            ];
        else if(strpos($path,'pickup/cancel') !== false)
            return [
                'carrier_id' => 'required|exists:pickups,carrier_id,id,'.$this->pickup_id,
                'pickup_id' => 'required|exists:pickups,id',
            ];
        return [];
    }
}