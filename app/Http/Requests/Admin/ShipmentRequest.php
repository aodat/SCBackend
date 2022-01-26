<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;

class ShipmentRequest extends FormRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        $data['merchant_id'] = $this->route('merchant_id');
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
        } else if ($this->method() == 'POST' && (strpos($path, 'shipments/track') !== false)) {
            return [
                'shipment_number' => 'required|exists:shipments,external_awb',
            ];
        } else if ($this->method() == 'PUT' && (strpos($path, '{merchant_id}/shipments/{shipment_id}') !== false)) {
            return [
                'amount' => 'required|min:0.01',
            ];
        }

        return [];
    }
}
