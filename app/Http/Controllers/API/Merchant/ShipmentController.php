<?php

namespace App\Http\Controllers\API\Merchant;


use App\Http\Requests\Merchant\ShipmentRequest;

use App\Jobs\ProcessShipCashUpdates;
use App\Jobs\CreateShipCashUpdates;

use App\Exports\ShipmentExport;


use App\Models\Carriers;
use App\Models\Shipment;

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
        $shipments = Shipment::whereBetween('created_at',[$since." 00:00:00",$until." 23:59:59"])->where('merchant_id',$request->user()->merchant_id);
        
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
    
        return $this->response($paginated,'Data Retrieved Successfully',200,true);

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
        return DB::transaction(function () use($request) {
            $shipmentRequest = $request->json()->all();
            return $this->shipment('EXP',$shipmentRequest);
        });
    }

    public function createDomesticShipment(ShipmentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $shipmentRequest = $request->json()->all();
            return $this->shipment('DOM',$shipmentRequest);
        });
    }

    public function shipment($type,$shipmentRequest)
    {
        $providerShipemntsArr = [];
        $usedProvider = [];
        $shipments = [];

        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);
        $dom_rates = collect($merchentInfo->domestic_rates);
        $providers = Carriers::pluck('name','id');
        
        foreach($shipmentRequest as $shipment)
        {
            $address = $merchentAddresses->where('id','=',$shipment['sender_address_id'])->first();
            if($address == null)
                return $this->error('Sender address id is in valid',422);
            if(!isset($merchentInfo['country_code']))
                return $this->error('Merchent country is empty',422);
    
            unset($shipment['sender_address_id']);

            $provider = $providers[$shipment['carrier_id']];

            $shipment['sender_email'] = $merchentInfo['email'];
            $shipment['sender_name'] = $merchentInfo['name'];
            $shipment['sender_phone'] = $address['phone'];
            $shipment['sender_country'] = $merchentInfo['country_code'];
            $shipment['sender_city'] = $address['city_code'];
            $shipment['sender_area'] = $address['area'];                
            $shipment['sender_address_description'] = $address['description'];

            $shipment['group'] = $type;
            if($type == 'DOM') {
                $shipment['consignee_country'] = $merchentInfo['country_code'];

                $domestic_rates = $dom_rates->where('code','=',$address['city_code'])->first();
                $shipment['fees'] = $domestic_rates['price'] ?? 0;
                if($shipment == 0)
                    return $this->error('Domestic Rates Is Zero');
            } else {
                $shipment['fees'] = $this->calculateFees($provider,$shipment['carrier_id'],$shipment['consignee_country'],$shipment['actual_weight']);
            }
            $shipment['internal_awb'] = generateBarcodeNumber();
            $shipment['merchant_id'] = Request()->user()->merchant_id;            
            $shipment['created_by'] = Request()->user()->id;

            $usedProvider[] = $provider;
            $providerShipemntsArr[$provider][] = $this->generateShipmentArray($provider,$address,$shipment);
            $shipments[$provider][] = $shipment;
        }
        
        $links = [];
        foreach($usedProvider as $provide){
            $result = $this->generateShipment($provide,$providerShipemntsArr[$provide]);
            $externalAWB = $result['id'];
            $ships = collect($shipments[$provide])->map(function ($value,$key) use($externalAWB){
                $value['external_awb'] = $externalAWB[$key];
                return $value;
            });
            $links[] = $result['link'];
            DB::table('shipments')->insert($ships->toArray());
        }
        if(count($links) == 1)
            return $this->response(['link' => $result['link']]);
        return $this->response(['link' => mergePDF($links)]);
    }

    public function printLabel(ShipmentRequest $request)
    {
        return $this->response(['link' => $this->printShipment('Aramex',$request->shipment_number)]);
    }

    public function shipmentProcessSQS(ShipmentRequest $request)
    {
        ProcessShipCashUpdates::dispatch($request->json()->all());
        return $this->successful('Webhook Completed');
    }
}
