<?php

namespace App\Http\Requests\Merchant;

use App\Rules\ShipmentRule;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rules\RequiredIf;

class ShipmentCreator extends MerchantRequest
{
    public function rules()
    {
        $path = Request()->route()->uri;
        $type = '';
        $isRequired = true;
        if (strpos($path, 'shipments/domestic/create') !== false) {
            $isRequired = false;
            $type = '*.';
        }

        $validation = [
            $type . 'carrier_id' => [
                'required',
                new ShipmentRule('carrier')
            ],
            $type . 'sender_address_id' => [
                'required',
                new ShipmentRule('address')
            ],
            $type . 'consignee_name' => 'required|max:255',
            $type . 'consignee_email' => ($isRequired ? 'required|' : '') . 'email',
            $type . 'consignee_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            $type . 'consignee_second_phone' => '',
            $type . 'consignee_notes' => [
                new ShipmentRule('word_count', ['min' => 0, 'max' => 10])
            ],
            $type . 'consignee_city' => 'required',
            $type . 'consignee_area' => 'required',
            $type . 'consignee_address_description_1' => [
                'required',
            ],
            $type . 'consignee_address_description_2' => '',
            $type . 'content' => 'required',
            $type . 'pieces' => 'required|integer',
            $type . 'actual_weight' => ($isRequired ? 'required|' : '') . 'numeric|between:0,9999',
            $type . 'reference' => '',
        ];

        if (strpos($path, 'shipments/domestic/create') !== false) {
            $validation['*'] = 'required|array|min:1|max:50';
            $validation[$type . 'extra_services'] = 'required|in:DOMCOD';
            $validation[$type . 'cod'] = 'required|numeric|between:0,750';
        } else if (strpos($path, 'shipments/express/create') !== false) {

            if (!empty(Request::instance()->dimention)) {
                $validation[$type . 'dimention.length'] = 'required|numeric|between:0,9999';
                $validation[$type . 'dimention.height'] = 'required|numeric|between:0,9999';
                $validation[$type . 'dimention.width'] = 'required|numeric|between:0,9999';
            }

            $validation[$type . 'cod'] = 'numeric|between:0,9999';
            $validation[$type . 'consignee_country'] = 'required';
            $validation[$type . 'is_doc'] = 'required|boolean';
            $validation[$type . 'declared_value'] = [
                new RequiredIf($this->is_doc == false),
                'numeric',
                'between:0,9999',
            ];
            $validation[$type . 'consignee_zip_code'] = '';
        }
        return $validation;
    }
}
