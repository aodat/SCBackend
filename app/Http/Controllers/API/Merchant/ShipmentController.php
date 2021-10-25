<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exports\ShipmentExport;
use App\Models\Shipment;

use App\Http\Requests\Merchant\ShipmentRequest;
use aramex;
use Carbon\Carbon;
use Illuminate\Support\Arr;

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
            $dom_rates = collect($merchentInfo->domestic_rates);

            $aramex = [];
            $ships = [];
            foreach($shipmentRequest as $shipment)
            {
                $address = $merchentAddresses->where('id','=',$shipment['sender_address_id'])->first();
                if($address == null)
                    return $this->error('Sender address id is in valid',400);
                if(!isset($merchentInfo['country_code']))
                    return $this->error('Merchent country is empty',400);
        
                unset($shipment['sender_address_id']);

                $shipment['sender_email'] = $merchentInfo['email'];
                $shipment['sender_name'] = $merchentInfo['name'];
                $shipment['sender_phone'] = $address['phone'];
                $shipment['sender_country'] = $merchentInfo['country_code'];
                $shipment['consignee_country'] = $merchentInfo->country_code;

                $shipment['sender_city'] = $address['city_code'];
                $shipment['sender_area'] = $address['area'];
                $shipment['sender_address_description'] = $address['description'];
                $shipment['group'] = 'DOM';
                $domestic_rates = $dom_rates->where('code','=',$address['city_code'])->first();
                $shipment['fees'] = $domestic_rates['price'] ?? 0;
                
                $shipment['merchant_id'] = Request()->user()->merchant_id;
                $shipment['internal_awb'] = abs(crc32(uniqid()));
                
                $shipment['created_by'] = Request()->user()->id;

                $aramix[] = $obj->shipmentArray($merchentInfo,$address,$shipment);
                $ships[] = $shipment;
            }
            $createShipments = $obj->createShipment($aramix);
            if($createShipments['HasErrors'])
                return $this->error($createShipments['Notifications']);

            $list = collect($createShipments['Shipments']);

            $externalAWB = $list->pluck('ID')->toArray();
            $ships = collect($ships)->map(function ($value,$key) use($externalAWB){
                $value['external_awb'] = $externalAWB[$key];
                return $value;
            });
            DB::table('shipments')->insert($ships->toArray());
            return $this->response(
                [
                    'link' => mergePDF(
                            $list
                            ->pluck('ShipmentLabel.LabelURL')
                            ->toArray()
                        )
                ]
            );
        });
    }

    public function printLabel(ShipmentRequest $request)
    {
        $shipment_numbers = $request->shipment_number;
        $obj = new aramex();
        $files = [];
        foreach($shipment_numbers as $shipment_number) {
            $data = $obj->printLabel($shipment_number);
            if(isset($data['ShipmentLabel']['LabelURL']))
                $files[] = $data['ShipmentLabel']['LabelURL'];
        }
        if(count($files) == 1)
            return $this->response(['link' => $files[0]]);
        else if(count($files) > 1)
            return $this->response(
                [
                    'link' => mergePDF($files)
                ]
            );
    }
}
