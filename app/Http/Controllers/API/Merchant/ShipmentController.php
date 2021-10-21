<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Shipment;
use App\Http\Requests\Merchant\ShipmentRequest;

class ShipmentController extends MerchantController
{
    
    public function index(ShipmentRequest $request)
    {

    }

    public function show($id,ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response(['msg' => 'Transaction Retrived Sucessfully','data' => $data],200);
    }
    
    public function store(ShipmentRequest $request)
    {

    }

    public function export(ShipmentRequest $request)
    {

    }
}
