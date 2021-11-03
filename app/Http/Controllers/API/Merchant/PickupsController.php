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
        $provider = Carriers::findOrfail($data['carrier_id'])->name;
        DB::transaction(function () use ($data, $address, $provider,$merchentInfo) {
            $pickupInfo = $this->generatePickup($provider, $data['pickup_date'], $address);

            $data['merchant_id'] = $merchentInfo->id;
            $data['hash'] = $pickupInfo['guid'];
            $data['cancel_ref'] = $pickupInfo['id'];

            Pickup::updateOrCreate(
                ['merchant_id' => $merchentInfo->id,'hash' => $pickupInfo['guid'],'carrier_id' => $data['carrier_id']],
                ['merchant_id' => $merchentInfo->id,'hash' => $pickupInfo['guid'], 'cancel_ref' => $pickupInfo['id'],'carrier_id' => $data['carrier_id'] , 'pickup_date' => $data['pickup_date'],'address_id' => $data['address_id']],
            );
        });

        return $this->successful();
    }

    public function cancel(PickuptRequest $request)
    {
        $lists = Pickup::getPickupCarrires($request->user()->merchant_id,$request->pickup_id,$request->carrier_id);
        $lists = $lists->groupBy('name');
        
        $lists->map(function($list,$provider){
            $list->map(function($pickup) use($provider){
                $this->cancelPickup($provider, $pickup);
            });
        });
        return $this->successful('The pickup has been canceled successfully');
    }
}
