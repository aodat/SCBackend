<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use App\Models\Pickup;

class PickupsController extends MerchantController
{
    public function index(PickuptRequest $request)
    {

    }

    public function store(PickuptRequest $request)
    {
        $data = $request->json()->all();
        $data['merchant_id'] = $request->user()->merchant_id;

        Pickup::create($data);
        return $this->successful('Data Created Sucessfully');
    }
}
