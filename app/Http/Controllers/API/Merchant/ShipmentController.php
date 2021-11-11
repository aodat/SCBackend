<?php

namespace App\Http\Controllers\API\Merchant;


use App\Http\Requests\Merchant\ShipmentRequest;

use App\Jobs\ProcessShipCashUpdates;

use App\Exports\ShipmentExport;


use App\Models\Carriers;
use App\Models\Shipment;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use App\Exceptions\InternalException;
use App\Models\Merchant;
use App\Models\Transaction;

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
        $shipments = Shipment::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"])->where('merchant_id', $request->user()->merchant_id);

        if (count($external))
            $shipments->whereIn('external_awb', $external);
        if (count($statuses))
            $shipments->whereIn('status', $statuses);
        if (count($phone))
            $shipments = $shipments->where(function ($query) use ($phone) {
                $query->whereIn('sender_phone', $phone)->orWhereIn('consignee_phone', $phone);
            });

        if ($operation)
            $shipments->where("cod", $operation, $cod);
        else if ($cod)
            $shipments->whereBetween('cod', [intval($cod), intval($cod) . '.99']);

        $paginated = $shipments->paginate(request()->perPage ?? 10);

        return $this->response($paginated, 'Data Retrieved Successfully', 200, true);
    }

    public function show($id, ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response($data, 'Data Retrieved Sucessfully', 200);
    }

    public function export($type, ShipmentRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $shipments = Shipment::find($merchentID);
        $path = "export/shipments-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = exportXLSX(new ShipmentExport($shipments), $path);
        } else {
            $url = exportPDF('shipments', $path, $shipments);
        }
        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }

    // Create Express Shipment will be one by one only 
    public function createExpressShipment(ShipmentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $shipmentRequest = $request->validated();
            $provider = Carriers::where('id', $shipmentRequest['carrier_id'])->first()->name;
            return $this->shipment('EXP', collect([$shipmentRequest]), $provider);
        });
    }

    public function createDomesticShipment(ShipmentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $shipmentRequest = $request->validated();
            return $this->shipment('DOM', collect($shipmentRequest), 'Aramex');
        });
    }

    private function shipment($type, $shipments, $provider = null)
    {
        $merchentInfo = $this->getMerchentInfo();
        $addresses = collect($merchentInfo->addresses);
        $dom_rates = collect($merchentInfo->domestic_rates);
        $shipments = $shipments->map(function ($shipment) use ($addresses, $merchentInfo, $provider, $dom_rates, $type) {
            $address = $addresses->where('id', '=', $shipment['sender_address_id'])->first();
            if ($address == null)
                throw new InternalException('Sender address id is in valid');
            if ($merchentInfo->country_code == null)
                throw new InternalException('Merchent country is empty');

            $shipment['sender_email'] = $merchentInfo['email'];
            $shipment['sender_name'] = $merchentInfo['name'];
            $shipment['sender_phone'] = $address['phone'];
            $shipment['sender_country'] = $merchentInfo['country_code'];
            $shipment['sender_city'] = $address['city_code'];
            $shipment['sender_area'] = $address['area'];
            $shipment['sender_address_description'] = $address['description'];

            unset($shipment['sender_address_id']);

            $shipment['group'] = $type;
            if ($type == 'DOM') {
                $shipment['consignee_country'] = $merchentInfo['country_code'];

                $domestic_rates = $dom_rates->where('code', '=', $address['city_code'])->first();
                $shipment['fees'] = $domestic_rates['price'] ?? 0;
                if ($shipment == 0)
                    throw new InternalException('Domestic Rates Is Zero');
            } else
                $shipment['fees'] = $this->calculateFees($provider, $shipment['carrier_id'], $shipment['consignee_country'], $shipment['actual_weight']);

            $shipment['merchant_id'] = Request()->user()->merchant_id;
            $shipment['created_by'] = Request()->user()->id;

            return $shipment;
        });

        return $this->createShipmentDB($shipments, $provider);
    }

    private function createShipmentDB($shipments, $provider)
    {
        $getbulk = $shipments->where('carrier_id', 1);
        $payloads = $getbulk->map(function ($data) {
            return $this->generateShipmentArray('Aramex', $data);
        });

        $resource = Request()->resource;
        $links = [];
        // for signle Shipment Request
        if ($payloads->isEmpty()) {
            $shipment = $shipments->toArray()[0];
            $result = $this->generateShipment($provider, $this->getMerchentInfo(), $shipment);
            $links[] = $result['link'];
            $shipment['external_awb'] = $result['id'];
            $shipment['resource'] = $resource;
            Shipment::create($shipment);
        }

        if (!$payloads->isEmpty()) {
            $result = $this->generateShipment('Aramex', $this->getMerchentInfo(), $payloads);
            $externalAWB = $result['id'];
            $ships = $shipments->map(function ($value, $key) use ($externalAWB,$resource){
                $value['external_awb'] = $externalAWB[$key];
                $value['resource'] = $resource;
                return $value;
            });
            $links[] = $result['link'];
            DB::table('shipments')->insert($ships->toArray());
        }

        return $this->response(['link' => mergePDF($links)]);
    }

    public function printLabel(ShipmentRequest $request)
    {
        return $this->response(['link' => $this->printShipment('Aramex', $request->shipment_number)]);
    }

    public function shipmentProcessSQS(ShipmentRequest $request)
    {
        ProcessShipCashUpdates::dispatch($request->json()->all());
        return $this->successful('Webhook Completed');
    }

    protected function transactionDeposit($shipment_id, $amount)
    {
        return DB::transaction(function () use ($shipment_id, $amount) {
            $merchent = Merchant::findOrFail(Request()->user()->merchant_id);
            $actual_balance = $merchent->actual_balance;
            $merchent->actual_balance = $actual_balance + $amount;
            $merchent->save();

            $carriers = Carriers::find($shipment_id);
            $carriers->balance = $carriers->balance - $amount;
            $carriers->save();

            Transaction::create([
                "type" => "CASHIN",
                "item_id" => $shipment_id,
                "merchant_id" => Request()->user()->merchant_id,
                "amount" => $merchent->actual_balance,
                "balance_after" => $actual_balance,
                "source" => "SHIPMENT",
                "created_by" => Request()->user()->id
            ]);
        });
    }
}
