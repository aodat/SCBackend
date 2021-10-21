<?php

namespace App\Http\Requests\Merchant;


class ShipmentRequest extends MerchantRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    
    public function rules()
    {
        $path = Request()->path();
        
        if($this->method() == 'POST' && strpos($path,'shipments/create') !== false)
            return [
                'carrier_id' => 'required|exists:carriers,id',
                'sender_address_id' => 'required',

                'consignee_name' => 'required|min:6|max:255',
                'consignee_email' => 'required|email',
                'consignee_phone' => 'required',
                'consignee_city' => 'required',
                'consignee_area' => 'required',
                'consignee_address_description' => 'required',

                'content' => 'required',

                'actual_weight' => 'required|numeric|between:0,9999',
                'cod' => 'required|numeric|between:0,9999',
                'pieces' => 'required|integer',
                'extra_services' => 'required|in:DOMCOD',
                'group' => 'required|in:EXP,DOM'
            ];
        else if($this->method() == 'POST' && strpos($path,'shipments') !== false) 
            return [
                'created_at.since' => 'nullable|date|date_format:Y-m-d',
                'created_at.until' => 'nullable|date|date_format:Y-m-d|after:created_at.since',
                'external' => 'Array',
                'statuses' => 'Array',
                'phone' => 'Array',
                'cod.val' =>  'nullable|numeric|between:1,999',
                'cod.operation' => 'nullable'
            ];
        return [];
    }
}
