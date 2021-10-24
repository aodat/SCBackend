<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use App\Models\Pickup;
use aramex;


class PickupsController extends MerchantController
{
    public function index(PickuptRequest $request)
    {

    }

    public function store(PickuptRequest $request)
    {
        // $obj = new aramex();
        // $obj->createPickup();

        $data = $request->json()->all();
        $data['merchant_id'] = $request->user()->merchant_id;

        Pickup::create($data);
        return $this->successful('Data Created Sucessfully');
    }
}
