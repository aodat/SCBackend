<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exports\ShipmentExport;
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
        $result = DB::transaction(function () use($request){
            $jsons = $request->json()->all();
            if(count($jsons) > 1)
                foreach($jsons as $json)
                    $result = $this->createDomesticShipment($json);
            else {
                $jsons = reset($jsons);
                if($jsons['group'] == 'EXP')
                    $result = $this->createExpressShipment();
                else if($jsons['group'] == 'DOM')
                    $result = $this->createDomesticShipment($jsons);
            }
            return $result;
        });
        
        return $result;
    }

    public function export($type,ShipmentRequest $request)
    {
        $merchentID = 1;// Request()->user()->merchant_id;
        $shipments = Shipment::find($merchentID);
        $path = "export/shipments-$merchentID-".Carbon::today()->format('Y-m-d').".$type";

        if($type == 'xlsx') {
            $url = exportXLSX(new ShipmentExport($shipments),$path);
        } else {
            $url = exportPDF('shipments',$path,$shipments);
        }        
        return $this->response(['link' => $url],200);
    }

    public function createExpressShipment()
    {

    }

    public function createDomesticShipment($data)
    {
        $merchentInfo = $this->getMerchentInfo();

        $address = collect($merchentInfo->addresses)->where('id','=',$data['sender_address_id'])->first();

        if($address == null)
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
        
        $domestic_rates = collect($merchentInfo->domestic_rates)->where('code','=',$address['city'])->first();
        $final['fees'] = $domestic_rates['price'] ?? 0;
        
        $final['merchant_id'] = Request()->user()->merchant_id;
        $final['internal_awb'] = abs( crc32( uniqid() ) );
        $final['created_by'] = Request()->user()->id;
        Shipment::create($final);

        return $this->successful();
    }
}
