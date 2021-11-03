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
        if($this->method() == 'POST' && (strpos($path,'shipments/express/create') !== false || strpos($path,'shipments/domestic/create') !== false)) {
            // Check the type of shipment
            $type = '';
            $condition = 'not_in';
            if(strpos($path,'shipments/domestic/create') !== false) {
                $condition = 'in';
                $type = '*.';
            }

            $validation = [
                    $type.'carrier_id' => 'required|exists:carriers,id|'.$condition.':1', // it's mean for aramex only
                    $type.'sender_address_id' => 'required',

                    $type.'consignee_name' => 'required|min:6|max:255',
                    $type.'consignee_email' => 'required|email',
                    $type.'consignee_phone' => 'required',
                    $type.'consignee_second_phone' => 'required',
                    $type.'consignee_city' => 'required',
                    $type.'consignee_area' => 'required',
                    $type.'consignee_address_description' => 'required',

                    $type.'content' => 'required',

                    $type.'cod' => 'required|numeric|between:0,9999',
                    $type.'pieces' => 'required|integer'
                ];
            if(strpos($path,'shipments/domestic/create') !== false) {
                $validation['*'] = 'required|array|min:1|max:50';
                $validation[$type.'extra_services'] = 'required|in:DOMCOD';
            } else if(strpos($path,'shipments/express/create') !== false ) {
                $validation[$type.'consignee_country'] = 'required';
                $validation[$type.'actual_weight'] = 'required|numeric|between:0,9999';
            }
            return $validation;
        }
        else if($this->method() == 'POST' && strpos($path,'shipments/filters') !== false) 
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
        else if($this->method() == 'POST' && strpos($path,'shipments/print') !== false)
            return [
                'shipment_number.*' => 'required|exists:shipments,external_awb'
            ];
        else if($this->method() == 'POST' && strpos(Request()->path(),'webhook/aramex'))
            return [
                "UpdateCode" => "required",
                "WaybillNumber" => "required|exists:shipments,external_awb",
                "Comment1" => "",
                "UpdateDateTime" => "required|date",
                "ProblemCode" => ""
            ];
        return [];
    }
}
