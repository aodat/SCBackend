<?php

namespace App\Http\Controllers\API\Shipment;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    
    public function getAllShipments(Request $request)
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
