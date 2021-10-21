<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Shipment;
use App\Http\Requests\Merchant\ShipmentRequest;
use Illuminate\Support\Facades\Response;

use Carbon\Carbon;

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
        $data = $request->json()->all();
        if($data['group'] == 'EXP')
            $result = $this->createExpressShipment();
        else if($data['group'] == 'DOM')
            $result = $this->createDomesticShipment($data);

        
        return $result;
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
        $merchentInfo = $this->getMerchentInfo();

        $address = collect($merchentInfo->addresses)->where('id','=',$data['sender_address_id'])->first();

        if(count($address) == 0)
            return $this->error(['msg' => 'sender address id is in valid'],400);
        if(!isset($merchentInfo['country_code']))
            return $this->error(['msg' => 'Merchent Country Is Empty'],400);

        unset($data['sender_address_id']);

        $final = $data;
        $final['sender_email'] = $merchentInfo['email'];
        $final['sender_name'] = $merchentInfo['name'];
        $final['sender_phone'] = $address['phone'];
        $final['sender_country'] = $merchentInfo['country_code'];
        $final['sender_city'] = $address['city'];
        $final['sender_area'] = $address['area'];
        $final['sender_address_description'] = $address['description'];
        $final['consignee_country'] = $merchentInfo->country_code;
        
        $dom_rates = collect($merchentInfo->dom_rates)->where('code','=',$final['consignee_country'])->first();
        $final['fees'] = $dom_rates['price'] ?? 0;
        
        $final['merchant_id'] = Request()->user()->merchant_id;
        $final['internal_awb'] = floor(time()-999999999);
        $final['created_by'] = Request()->user()->id;
        Shipment::create($final);

        return $this->successful();
    }
}
