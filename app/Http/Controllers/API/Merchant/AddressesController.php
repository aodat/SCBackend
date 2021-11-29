<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Http\Requests\Merchant\AddressesRequest;

use App\Models\Merchant;
use Carbon\Carbon;

class AddressesController extends MerchantController
{
    public function index(AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id', $merchantID)->select('addresses')->first();
        return $this->response($data->addresses, 'Data Retrieved Successfully');
    }

    public function store(AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $json = $request->json()->all();

        $merchant = Merchant::where('id', $merchantID);

        $result = collect($merchant->select('addresses')->first()->addresses);
        
        if ($result->contains("name", $request->name))
            throw new InternalException('name already Exists',400);

        $counter = $result->max('id') ?? 0;
        $json['id'] = ++$counter;
        $json['country'] = $request->country ?? 'JO';
        $json['is_default'] = $request->is_default ?? false;
        $json['created_at'] = Carbon::now();
        $merchant->update(['addresses' => $result->merge([$json])]);
        return $this->successful('Create Successfully');
    }

    public function delete($id, AddressesRequest $request)
    {
        $merchantID = $request->user()->merchant_id;

        $list = Merchant::where('id', $merchantID);
        $result = collect($list->select('addresses')->first()->addresses);

        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['addresses' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
