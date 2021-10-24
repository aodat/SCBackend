<?php

namespace App\Http\Requests\Merchant;


class ShipmentRequest extends MerchantRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'GET' && strpos($path,'shipments/export/{type}') !== false)
            $data['type'] = $this->route('type');
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
        
        if(
            $this->method() == 'POST' && 
            (   
                strpos($path,'shipments/express/create') !== false ||
                strpos($path,'shipments/domestic/create') !== false
            )
        )
            return [
                '*.carrier_id' => 'required|exists:carriers,id',
                '*.sender_address_id' => 'required',

                '*.consignee_name' => 'required|min:6|max:255',
                '*.consignee_email' => 'required|email',
                '*.consignee_phone' => 'required',
                '*.consignee_city' => 'required',
                '*.consignee_area' => 'required',
                '*.consignee_address_description' => 'required',

                '*.content' => 'required',

                '*.cod' => 'required|numeric|between:0,9999',
                '*.pieces' => 'required|integer',
                '*.extra_services' => 'required|in:DOMCOD',
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
        else if($this->method() == 'GET' && strpos($path,'shipments/export/{type}') !== false)
            return [
                'type' => 'in:xlsx,pdf'
            ];
        return [];
    }
}
