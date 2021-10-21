<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Shipment;
use App\Http\Requests\Merchant\ShipmentRequest;
use Illuminate\Support\Facades\DB;

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
        $data = $request->json()->all();
        DB::transaction(function () use($data,$request) {
            $data['merchant_id'] = $request->user()->merchant_id;
            $data['internal_awb'] = floor(time()-999999999);
            $data['created_by'] = $request->user()->id;
            Shipment::create($data);
        });

        return $this->response(null,204);
    }

    public function export(ShipmentRequest $request)
    {

    }
}
