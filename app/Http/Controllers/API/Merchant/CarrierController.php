<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\CarrierRequest;
use App\Models\Carriers;
use App\Models\Merchant;
use Carbon\Carbon;

class CarrierController extends MerchantController
{
    //
    public function index()
    {
        $carriers =  Carriers::all();
        return $this->response($carriers, 'Data Retrieved Successfully');
    }

    public function update($carrier_id, CarrierRequest  $request)
    {
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->carriers)->unique('carrier_id');
        $carrier = $result->where('carrier_id', $carrier_id);


        if ($carrier->count() == 0) {
            $data = [
                'carrier_id' => $carrier_id,
                'is_defult' => $request->is_defult,
                'is_enabled' => $request->is_enabled,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $result = $result->merge([$data]);
        } else {

            $key = $carrier->keys()->first();

            $result->put($key, [

                'carrier_id' => $carrier_id,
                'is_defult' => $request->is_defult,
                'is_enabled' => $request->is_enabled,
                'created_at' => $result[$key]['created_at'],
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
        }
        $merchant->update(['carriers' => $result]);


        return $this->successful('Updated Successfully');
    }
}
