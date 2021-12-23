<?php

namespace App\Http\Controllers\API\Merchant;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\Merchant\ShipmentRequest;

use App\Jobs\ProcessShipCashUpdates;

use App\Exports\ShipmentExport;

use Carbon\Carbon;

use App\Exceptions\InternalException;

use App\Models\Transaction;
use App\Models\Carriers;
use App\Models\Shipment;

use Illuminate\Support\Facades\App;

class ShipmentController extends MerchantController
{

    private $status = [
        'DRAFT' => 0, 'PROCESSING' => 0, 'COMPLETED' => 0, 'RENTURND' => 0
    ];

    public function index(ShipmentRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subDays(3)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $external = $filters['external'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $phone = $filters['phone'] ?? [];
        $cod    = $filters['cod']['val'] ?? null;
        $operation    = $filters['cod']['operation'] ?? null;
        $type = $request->type ?? 'DOM';
        $shipments = Shipment::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);

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

        $shipments->where('group', $type);

        $tabs = DB::table('shipments')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->where('group', $type)
            ->groupBy('status')
            ->pluck('counter', 'status');

        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($shipments->paginate(request()->per_page ?? 10), ['tabs' => $tabs]);
    }

    public function show($id, ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        return $this->response($data, 'Data Retrieved Sucessfully');
    }

    public function export($type, ShipmentRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $shipments = Shipment::where('merchant_id', $merchentID)->get();
        $path = "export/shipments-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx')
            $url = exportXLSX(new ShipmentExport($shipments), $path);
        else
            $url = exportPDF('shipments', $path, $shipments);

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
            // Check Domastic
            $shipmentRequest = $request->validated();
            $addressList = App::make('merchantAddresses');
            $merchantInfo = App::make('merchantInfo');
            (collect($shipmentRequest)->pluck('sender_address_id'))->map(function ($address_id) use ($merchantInfo, $addressList) {
                if ($addressList->where('id', $address_id)->where('country_code', $merchantInfo->country_code)->isEmpty())
                    throw new InternalException('This is not Domestic request the merchant code different with send country code');
            });
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
            $shipment['sender_city'] = $address['city'];
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
                $shipment['fees'] = $this->calculateFees($provider, $shipment['sender_country'], $shipment['consignee_country'], $shipment['actual_weight']);

            $shipment['merchant_id'] = Request()->user()->merchant_id;
            $shipment['created_by'] = Request()->user()->id;
            $shipment['logs'] = json_encode([
                [
                    'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'UpdateLocation' => $shipment['consignee_address_description'] ?: '',
                    'UpdateDescription' => 'Create Shipment'
                ]
            ]);
            $shipment['created_at'] = Carbon::now();
            $shipment['updated_at'] = Carbon::now();
            return $shipment;
        });
        return $this->createShipmentDB($shipments, $provider);
    }

    private function createShipmentDB($shipments, $provider)
    {
        $resource = Request()->header('agent') ?? 'API';

        $getbulk = $shipments->where('carrier_id', 1);
        $payloads = $getbulk->map(function ($data) {
            return $this->generateShipmentArray('Aramex', $data);
        });


        $links = [];
        // for signle Shipment Request
        if ($payloads->isEmpty()) {
            $shipment = $shipments->toArray()[0];
            $result = $this->generateShipment($provider, $this->getMerchentInfo(), $shipment);
            $links[] = $result['link'];

            $shipment['external_awb'] = $result['id'];
            $shipment['resource'] = $resource;
            $shipment['url'] = $result['link'];
            Shipment::create($shipment);
        }

        if (!$payloads->isEmpty()) {
            $result = $this->generateShipment('Aramex', $this->getMerchentInfo(), $payloads);

            $externalAWB = $result['id'];
            $files = $result['link'];
            $shipments = $shipments->map(function ($value, $key) use ($externalAWB, $resource, $files) {
                $value['external_awb'] = $externalAWB[$key];
                $value['resource'] = $resource;
                $value['url'] = $files[$key];
                return $value;
            });
            $links = array_merge($links, $result['link']);
            Shipment::insert($shipments->toArray());
            // DB::table('shipments')->insert();
        }

        return $this->response(
            ['link' => mergePDF($links)],
            'Shipment Created Successfully'
        );
    }

    public function printLabel(ShipmentRequest $request)
    {
        return $this->response(
            ['link' => $this->printShipment($request->shipment_number)],
            'Labels returned successfully'
        );
    }

    public function shipmentProcessSQS(ShipmentRequest $request)
    {
        ProcessShipCashUpdates::dispatch($request->json()->all());
        return $this->successful('Webhook Completed');
    }

    protected function transactionDeposit($shipment_id, $amount)
    {
        return DB::transaction(function () use ($shipment_id, $amount) {
            $merchent = $this->getMerchentInfo();
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

    public function calculate(ShipmentRequest $request)
    {
        $data = $request->validated();
        $result = [];
        $car = Carriers::get()->where('is_cod', $data['is_cod'])->map(function ($carrier) use ($data, &$result) {
            $carrier['fees'] = $this->calculateFees($carrier->id, $data['country_code'], $data['type'], $data['weight']);

            return $carrier;
        });
        return $this->response($car, 'Fees Calculated Successfully');
    }

    // public function test(ShipmentRequest $request)
    // {
    //     $data = $request->data;
    //     $shipper = App::make('merchantInfo');
    //     $address = App::make('merchantAddresses')->where('is_default', true)->first();

    //     $shipment = [];
    //     $shipment['sender_name'] = $shipper->name;
    //     $shipment['sender_email'] = $shipper->email;
    //     $shipment['sender_phone'] = $shipper->phone;
    //     $shipment['sender_country'] = $shipper->country_code;
    //     $shipment['sender_city'] = $address['city'];
    //     $shipment['sender_area'] = $address['area'];
    //     $shipment['sender_address_description'] = $address['area'];

    //     $shipment['consignee_name'] = $data['customer']['name'];
    //     $shipment['consignee_email']  = $data['customer']['email'] ?? 'salla@shipcash.net';
    //     $shipment['consignee_phone']  = $data['customer']['mobile'];
    //     $shipment['consignee_second_phone'] = '';
    //     $shipment['consignee_country'] = $data['address']['country'];
    //     $shipment['consignee_city'] = $data['address']['city'];
    //     $shipment['consignee_area'] = $data['address']['shipping_address'];
    //     $shipment['consignee_zip_code'] = '';
    //     $shipment['consignee_address_description'] = $data['address']['shipping_address'];
    //     $shipment['content'] = 'Salla Webhook';
    //     $shipment['cod'] = $data['amounts']['total']['amount'];
    //     $shipment['currency'] = $data['amounts']['total']['currency'];
    //     $shipment['actual_weight'] = collect($data['items'])->sum('weight');
    //     $shipment['pieces'] = collect($data['items'])->count();

    //     $provider = $this->getActionShipments($shipment);
    //     $shipment['fees'] = $this->calculateFees($provider, $shipment['sender_country'], $shipment['consignee_country'], $shipment['actual_weight']);
    //     $shipment['merchant_id'] = Request()->user()->merchant_id;
    //     $shipment['created_by'] = Request()->user()->id;


    //     return $this->createShipmentDB($shipment, $provider);
    // }
}
