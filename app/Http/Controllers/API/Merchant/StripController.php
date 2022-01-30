<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

class StripController extends Controller
{

    public function strip($shipmentID, Request $request)
    {
        $shipment = Shipment::find($shipmentID);
        return view('strip.shipment')->with('shipment', $shipment);
    }
}
