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
                $data['since_at'] = Carbon::parse($data['since_at'])->startOfDay()->format('Y-m-d H:i:s');
                $data['until'] = Carbon::parse($data['until'])->endOfDay()->format('Y-m-d H:i:s');
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
                'until' => 'date|after:since_at',
            ];
        }
        return [];
    }
}
