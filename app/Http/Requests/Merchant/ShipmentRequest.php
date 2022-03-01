<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rules\RequiredIf;

class ShipmentRequest extends MerchantRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'GET' && strpos($path, 'shipments/export/{type}') !== false) {
            $data['type'] = $this->route('type');
        }

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
        if ($this->method() == 'POST' && strpos($path, 'shipments/filters') !== false) {
            return [
                'created_at.since' => 'nullable|date|date_format:Y-m-d',
                'created_at.until' => 'nullable|date|date_format:Y-m-d|after:created_at.since',
                'external' => 'array',
                'statuses' => 'array',
                'phone' => 'array',
                'cod.val' => 'nullable|numeric|between:1,999',
                'cod.operation' => 'nullable',
            ];
        } else if ($this->method() == 'GET' && strpos($path, 'shipments/export/{type}') !== false) {
            return [
                'type' => 'in:xlsx,pdf',
            ];
        } else if ($this->method() == 'POST' && strpos($path, 'shipments/print') !== false) {
            return [
                'shipment_number.*' => 'required|exists:shipments,awb',
            ];
        } else if ($this->method() == 'POST' && strpos($path, 'shipments/calculate/fees')) {
            $type = Request::instance()->type;
            $validation = [
                'weight' => 'required|numeric|between:0,9999',
                'type' => 'required|in:express,domestic',
                'is_cod' => 'required|boolean',
            ];
            if ($type == 'express') {
                $validation['country_code'] = 'required';
                if (!empty(Request::instance()->dimention)) {
                    $validation['dimention.length'] = 'required|numeric|between:0,9999';
                    $validation['dimention.height'] = 'required|numeric|between:0,9999';
                    $validation['dimention.width'] = 'required|numeric|between:0,9999';
                }
            } else {
                $validation['city_from'] = 'required';
                $validation['city_to'] = 'required';
            }
            return $validation;
        } else if ($this->method() == 'POST' && (strpos($path, 'shipments/create') !== false)) {
            dd('Error ');
            $validation = [
                'strip_token' => 'required',
                'type' => 'required|in:express,domestic',
                'carrier_id' => [
                    'required',
                    'exists:carriers,id,is_active,1',
                ],
                'sender_email' => 'required|email',
                'sender_name' => [
                    'required',
                    // new wordCount(2),
                ],
                'sender_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14',
                'sender_country' => 'required',
                'sender_city' => 'required',
                'sender_area' => 'required',
                'sender_address_description' => 'required',

                'consignee_name' => 'required|max:255',
                'consignee_email' => 'email',
                'consignee_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
                'consignee_notes' => [
                    // new wordCount(1, 10),
                ],
                'consignee_country' => 'required',
                'consignee_city' => 'required',
                'consignee_area' => 'required',
                'consignee_address_description_1' => [
                    'required',
                ],
                'consignee_address_description_2' => '',
                'content' => [
                    'required',
                ],
                'pieces' => 'required|integer',
                'consignee_zip_code' => '',
                'actual_weight' => 'required|numeric|between:0,9999',
            ];

            $type = Request::instance()->type;
            if ($type == 'express') {
                $validation['cod'] = 'numeric|between:0,9999';
                $validation['payment'] = 'numeric|between:0,9999';
                $validation['consignee_country'] = 'required';
                $validation['actual_weight'] = 'required|numeric|between:0,9999';
                $validation['is_doc'] = 'required|boolean';
                $validation['declared_value'] = [
                    new RequiredIf($this->is_doc == false),
                    'numeric',
                    'between:0,9999',
                ];
                $validation['consignee_zip_code'] = '';
                $validation['consignee_second_phone'] = '';
            } else {
                $validation['cod'] = 'required|numeric|between:0,9999';
                $validation['extra_services'] = 'required|in:DOMCOD';
            }
            return $validation;
        } else if ($this->method() == 'POST' && (strpos($path, 'shipments/track') !== false)) {
            return [
                'shipment_number' => 'required|exists:shipments,awb',
            ];
        }

        return [];
    }
}
