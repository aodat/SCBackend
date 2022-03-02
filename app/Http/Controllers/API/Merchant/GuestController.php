<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\GuestRequest;
use App\Models\Carriers;
use Illuminate\Http\Request;

class GuestController extends MerchantController
{
    public function calculate(GuestRequest $request)
    {
        $data = $request->validated();
        $carriers = Carriers::where('is_active', true)
            ->where($data['type'], true);

        if ($data['is_cod']) {
            $carriers->where('accept_cod', $data['is_cod']);
        }

        $dimention = $request->dimention ?? [];
        $carrier = $carriers->get()->map(function ($carrier) use ($data, $dimention) {
            if ($data['type'] == 'express') {
                $carrier['fees'] = number_format((new ShipmentController)->calculateExpressFees(
                    $carrier->id,
                    $data['country_code'],
                    $data['weight'],
                    env('GUEST_MERCHANT_ID'),
                    $dimention
                ), 2);
            } else {
                $carrier['fees'] = number_format((new ShipmentController)->calculateDomesticFees(
                    $carrier->id,
                    $data['city_to'],
                    $data['weight'],
                    env('GUEST_MERCHANT_ID')
                ), 2);
            }

            return $carrier;
        })->reject(function ($carrier) {
            return floatval($carrier['fees']) <= 0;
        });
        return $this->response($carrier->flatten(), 'Fees Calculated Successfully');
    }
}
