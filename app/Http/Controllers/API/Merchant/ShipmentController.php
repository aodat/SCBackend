<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Shipment;
use App\Http\Requests\Merchant\ShipmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShipmentController extends MerchantController
{
    
    public function index(ShipmentRequest $request)
    {
        $filters = $request->json()->all();
 
        $since = $filters['created_at']['since'] ?? Carbon::today()->subDays(3)->format('Y-m-d');;
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $external = $filters['external'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $phone = $filters['phone'] ?? [];
        $cod    = $filters['cod']['val'] ?? null;
        $operation    = $filters['cod']['operation'] ?? null;

        $shipments = Shipment::whereBetween('created_at',[$since." 00:00:00",$until." 23:59:59"]);
        
        if(count($external))
            $shipments->whereIn('external_awb',$external);
        if(count($statuses))
            $shipments->whereIn('status',$statuses);
        if(count($phone))
            $shipments = $shipments->where(function($query) use ($phone) {
                $query->whereIn('sender_phone',$phone)->orWhereIn('consignee_phone',$phone);
            });

        if($operation)
            $shipments->where("cod",$operation, $cod);
        else if($cod)
            $shipments->whereBetween('cod', [intval($cod), intval($cod).'.99']);

        $paginated = $shipments->paginate(request()->perPage ?? 10);
    
        return $this->response($paginated,200);

    }

    public function show($id,ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response(['msg' => 'Transaction Retrived Sucessfully','data' => $data],200);
    }
    
    public function store(ShipmentRequest $request)
    {
        /*
        "consignee_country": "DU", Domastic => merchant Country
        $data['merchant_id'] = $request->user()->merchant_id;
        $data['internal_awb'] = floor(time()-999999999);
        $data['created_by'] = $request->user()->id;
        Shipment::create($data);
        */
        $data = $request->json()->all();
        DB::transaction(function () use($data) {
            if($data['group'] == 'EXP')
                $this->createExpressShipment();
            else if($data['group'] == 'DOM')
                $this->createDomesticShipment($data);
        });

        return $this->successful(null,204);
    }

    public function export(ShipmentRequest $request)
    {
        return [];
    }

    public function createExpressShipment()
    {

    }

    public function createDomesticShipment($data)
    {
        
        dd($data);

        // get fees from price 

        // consignee_country from merchant id

    }
}
