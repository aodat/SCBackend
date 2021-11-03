<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use Illuminate\Support\Facades\DB;

use App\Models\Pickup;
use App\Models\Carriers;

class PickupsController extends MerchantController
{
    public function index(PickuptRequest $request)
    {
    }

    public function store(PickuptRequest $request)
    {
        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);

        $data = $request->validated();
        $address = $merchentAddresses->where('id', '=', $data['address_id'])->first();
        if ($address == null)
            return $this->error('address id is not valid', 400);
        unset($data['address_id']);

        $provider = Carriers::findOrfail($data['carrier_id'])->name;
        DB::transaction(function () use ($data, $address, $provider,$merchentInfo) {
            $pickupInfo = $this->generatePickup($provider, $data['pickup_date'], $address);

            $data['merchant_id'] = $merchentInfo->id;
            $data['hash'] = $pickupInfo['guid'];
            $data['cancel_ref'] = $pickupInfo['id'];

            Pickup::updateOrCreate(
                ['merchant_id' => $merchentInfo->id,'hash' => $pickupInfo['guid'],'carrier_id' => $data['carrier_id']],
                ['merchant_id' => $merchentInfo->id,'hash' => $pickupInfo['guid'], 'cancel_ref' => $pickupInfo['id'],'carrier_id' => $data['carrier_id'] , 'pickup_date' => $data['pickup_date']],
            );
        });

        return $this->successful();
    }

    public function cancel(PickuptRequest $request)
    {
        $data = Pickup::where('merchant_id', Request()->user()->merchant_id)
            ->where('carrier_id', $request->carrier_id)
            ->where('id', $request->pickup_id)
            ->select('hash')
            ->first();
        if ($data == null)
            $this->error('requested data invalid');

        $this->cancelPickup('Aramex', $data->hash);
        return $this->successful('The pickup has been canceled successfully');
    }
}
