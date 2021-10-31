<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use App\Models\Carriers;
use Illuminate\Support\Facades\DB;
use App\Models\Pickup;

class PickupsController extends MerchantController
{
    public function index(PickuptRequest $request)
    {

    }

    public function store(PickuptRequest $request)
    {
        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);
        
        $address = $merchentAddresses->where('id','=',$request->address_id)->first();
        if($address == null)
            return $this->error('address id is not valid',400);

        $type = Carriers::findOrfail($request->carrier_id)->name;
        $final = DB::transaction(function () use($request,$address,$type) {
            $data = $request->json()->all();
            $pickupInfo = $this->generatePickup($type,$request->pickup_date,$address);
    
            $data['merchant_id'] = $request->user()->merchant_id;
            $data['hash'] = $pickupInfo['guid'];
            $data['cancel_ref'] = $pickupInfo['id'];
    
            Pickup::create($data);
        });

        return $this->response($final);
    }

    public function cancel(PickuptRequest $request)
    {
        $data = Pickup::where('merchant_id',Request()->user()->merchant_id)
            ->where('carrier_id',$request->carrier_id)
            ->where('id',$request->pickup_id)
            ->select('hash')
            ->first();
        if($data == null)
            $this->error('requested data invalid');

        $this->cancelPickup('Aramex',$data->hash);
        return $this->successful('The pickup has been canceled successfully');
    }
}
