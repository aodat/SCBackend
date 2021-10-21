<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends MerchantController
{
    
    public function index(Request $request)
    {

    }

    public function getShipment($id,Request $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response(['msg' => 'Transaction Retrived Sucessfully','data' => $data],200);
    }
    
    public function withDraw(Request $request)
    {

    }

    public function export(Request $request)
    {

    }
}
