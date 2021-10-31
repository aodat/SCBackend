<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\AddressesRequest;

use App\Models\Merchant;
use Carbon\Carbon;

class AddressesController extends MerchantController
{
    public function index(AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('addresses')->first();

      
        if(collect($data->addresses)->isEmpty())
            return $this->notFound();

        return $this->response($data->addresses,'Addresses Retrieved Successfully',200);
    }

    public function createAddresses(AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $json = $request->json()->all();
        
        $merchant = Merchant::where('id',$merchantID);

        $result = collect($merchant->select('addresses')->first()->addresses);
        $counter = $result->max('id') ?? 0;
        $json['id'] = ++$counter;
        $json['country'] = $request->country ?? 'JO';
        $json['created_at'] = Carbon::now();
        $merchant->update(['addresses' => $result->merge([$json])]);
        return $this->successful();
    }

    public function deleteAddresses($id,AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        
        $list = Merchant::where('id',$merchantID);
        $result = collect($list->select('addresses')->first()->addresses);

        $json = $result->reject(function ($value) use($id) {
            if($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['addresses' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
