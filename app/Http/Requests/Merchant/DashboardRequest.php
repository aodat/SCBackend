<?php

namespace App\Http\Requests\Merchant;

use Carbon\Carbon;

class DashboardRequest extends MerchantRequest
{
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'POST' && strpos($path, 'merchant/dashboard') !== false) {
            if ($data['since_at'] !== null && $data['until'] !== null) {
                $data['since_at'] = date("Y-m-d H:i:s", strtotime($data['since_at']));
                $data['until'] = date("Y-m-d H:i:s", strtotime($data['until']));
            } else {
                $data['since_at'] = Carbon::now()->subDays(7);
                $data['until'] = Carbon::now();
            }
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
        $path = Request()->path();

        if (strpos($path, 'merchant/dashboard') !== false) {
            return [
                'since_at' => 'date',
                'until' => 'date|after:since_at'
            ];
        }
        return [];
    }
}