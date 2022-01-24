<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\CarrierRequest;
use App\Models\Carriers;
use Carbon\Carbon;

class CarrierController extends MerchantController
{
    //
    public function index()
    {
        $carriers = Carriers::all();
        return $this->response($carriers, 'Data Retrieved Successfully');
    }

    public function update(CarrierRequest $request)
    {
        $carrier_id = $request->carrier_id;
        $name = Carriers::find($carrier_id)->name;
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->carriers)->unique('carrier_id');
        $carrier = $result->where('carrier_id', $carrier_id);

        // Check the ENV Files
        if (!$this->check($name, $request->env)) {
            return $this->error("Invalid $name Configuration Keys", 500);
        }

        if ($carrier->count() == 0) {
            $data = [
                'carrier_id' => $carrier_id,
                'is_defult' => $request->is_defult ?? false,
                'is_enabled' => $request->is_enabled ?? true,
                'env' => collect($request->env),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            $result = $result->merge([$data]);
        } else {
            $key = $carrier->keys()->first();
            $result->put($key, [
                'carrier_id' => $carrier_id,
                'is_defult' => $request->is_defult ?? $result[$key]['is_defult'],
                'is_enabled' => $request->is_enabled ?? $result[$key]['is_enabled'],
                'env' => collect($request->env)->isEmpty() ? collect($result[$key]['env']) : collect($request->env),
                'created_at' => $result[$key]['created_at'],
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        }
        $merchant->update(['carriers' => $result]);

        return $this->successful('Updated Successfully');
    }

    public function delete(CarrierRequest $request)
    {
        $carrier_id = $request->carrier_id;
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->carriers)->unique('carrier_id');
        $carrier = $result->where('carrier_id', $carrier_id);

        $key = $carrier->keys()->first();
        $result->put($key, [
            'carrier_id' => $carrier_id,
            'is_defult' => $result[$key]['is_defult'],
            'is_enabled' => $result[$key]['is_enabled'],
            'env' => null,
            'created_at' => $result[$key]['created_at'],
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $merchant->update(['carriers' => $result]);
        return $this->successful('Removed Successfully');
    }
}
