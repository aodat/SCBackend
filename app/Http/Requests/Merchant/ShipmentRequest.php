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
        }
        return [];
    }
}
