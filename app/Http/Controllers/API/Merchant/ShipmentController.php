<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exports\ShipmentExport;
use App\Models\Shipment;

use App\Http\Requests\Merchant\ShipmentRequest;
use aramex;
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
    
        return $this->response($paginated,'Data Retrieved Successfully',200);

    }

    public function show($id,ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response($data,'Data Retrieved Sucessfully',200);
    }

    public function export($type,ShipmentRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $shipments = Shipment::find($merchentID);
        $path = "export/shipments-$merchentID-".Carbon::today()->format('Y-m-d').".$type";

        if($type == 'xlsx') {
            $url = exportXLSX(new ShipmentExport($shipments),$path);
        } else {
            $url = exportPDF('shipments',$path,$shipments);
        }        
        return $this->response(['link' => $url],'Data Retrieved Sucessfully',200);
    }

    public function createExpressShipment(ShipmentRequest $request)
    {
        $shipmentRequest = $request->json()->all();
        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);
        // foreach($shipmentRequest as $shipment) {}
    }

    public function createDomesticShipment(ShipmentRequest $request)
    {
        $obj = new aramex();
        return DB::transaction(function () use ($request,$obj) {
            $shipmentRequest = $request->json()->all();
            $merchentInfo = $this->getMerchentInfo();
            $merchentAddresses = collect($merchentInfo->addresses);
            foreach($shipmentRequest as $shipment)
            {

                $address = $merchentAddresses->where('id','=',$shipment['sender_address_id'])->first();
                if($address == null)
                    return $this->error('Sender address id is in valid',400);
                if(!isset($merchentInfo['country_code']))
                    return $this->error('Merchent country is empty',400);
        
                unset($shipment['sender_address_id']);

                $final = $shipment;
                $final['sender_email'] = $merchentInfo['email'];
                $final['sender_name'] = $merchentInfo['name'];
                $final['sender_phone'] = $address['phone'];
                $final['sender_country'] = $merchentInfo['country_code'];
                $final['sender_city'] = $address['city_code'];
                $final['sender_area'] = $address['area'];
                $final['sender_address_description'] = $address['description'];
                $final['consignee_country'] = $merchentInfo->country_code;
                $final['group'] = 'DOM';
                $domestic_rates = collect($merchentInfo->domestic_rates)->where('code','=',$address['city_code'])->first();
                $final['fees'] = $domestic_rates['price'] ?? 0;
                
                $final['merchant_id'] = Request()->user()->merchant_id;
                $final['internal_awb'] = abs(crc32(uniqid()));
                $final['created_by'] = Request()->user()->id;

                $obj->createShipment($merchentInfo,$address,$final);
                //         "LabelURL" => "https://ws.aramex.net/content/rpt_cache/97abe06699ec4eeaac7ced7bf97d65ad.pdf"
                //       "ID" => "46594921133" // external_awb 

                Shipment::create($final);
            }
            return $this->successful();
        });
    }
}
