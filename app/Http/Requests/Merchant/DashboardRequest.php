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
            if ((isset($data['since_at']) && isset($data['until'])) && ($data['since_at'] !== null && $data['until'] !== null)) {
                $data['since_at'] = Carbon::parse($data['since_at'])->format('Y-m-d');
                $data['until'] = Carbon::parse($data['until'])->format('Y-m-d');
            } else {
                $data['since_at'] = Carbon::now()->subYear(1);
                $data['until'] = Carbon::tomorrow();
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
                'until' => 'date|after_or_equal:since_at',
            ];
        }
        return [];
    }
}
