<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Pickup;
use aramex;


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
            return $this->error('address id is in valid',400);

        $final = DB::transaction(function () use($merchentInfo,$request,$address) {
            $obj = new aramex();
            $result = $obj->createPickup($merchentInfo->email,$request->pickup_date,$address);
            if($result['HasErrors'])
                return $result['Notifications'];
            
            $data = $request->json()->all();
    
            $data['merchant_id'] = $request->user()->merchant_id;
            $data['hash'] = $result['GUID'];
            $data['cancel_ref'] = $result['ID'];
    
            Pickup::create($data);
            return [];
        });

        return $this->response($final);
    }
}
