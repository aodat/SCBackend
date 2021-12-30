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
use App\Models\City;
use App\Models\Country;
use App\Models\Invoices;
use App\Models\Merchant;
use App\Models\Shipment;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class ShipmentController extends MerchantController
{

    private $status = [
        'DRAFT' => 0, 'PROCESSING' => 0, 'COMPLETED' => 0, 'RENTURND' => 0
    ];

    public function index(ShipmentRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
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
        return $this->pagination($shipments->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
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
        $countries = Country::pluck('code', 'name_en');
        $merchentInfo = $this->getMerchentInfo();
        $addresses = collect($merchentInfo->addresses);
        $dom_rates = collect($merchentInfo->domestic_rates);

        $shipments = $shipments->map(function ($shipment) use ($addresses, $merchentInfo, $dom_rates, $type, $countries) {
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
            } else {
                $shipment['consignee_country'] = $countries[$shipment['consignee_country']] ?? null;
                $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_country'], 'express', $shipment['actual_weight']);
            }

            $shipment['merchant_id'] = Request()->user()->merchant_id;
            $shipment['created_by'] = Request()->user()->id;
            $shipment['status'] = 'DRAFT';
            $shipment['logs'] = collect([
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
        if ($payloads->isEmpty()) { // for signle Shipment Request
            if (isset($shipments->toArray()[0]))
                $shipment = $shipments->toArray()[0];
            else
                $shipment = $shipments->toArray();
            $result = $this->generateShipment($provider, $this->getMerchentInfo(), $shipment);
            $links[] = $result['link'];

            $shipment['external_awb'] = $result['id'];
            $shipment['resource'] = $resource;
            $shipment['url'] = mergePDF([$result['link']]);

            $payment = null;
            if (isset($shipment['payment'])) {
                $payment = $shipment['payment'];
                unset($shipment['payment']);
            }

            Shipment::withoutGlobalScope('ancient')->create($shipment);
            if ($payment)
                Invoices::create([
                    "merchant_id" => Request()->user()->merchant_id,
                    "user_id" => Request()->user()->id,
                    "fk_id" => Shipment::select('id')->first()->id,
                    "customer_name" => $shipment['consignee_name'],
                    "customer_email" => $shipment['consignee_email'],
                    "description" => $shipment['consignee_notes'],
                    "amount" => $payment
                ]);
        }

        if (!$payloads->isEmpty()) {
            $result = $this->generateShipment('Aramex', $this->getMerchentInfo(), $payloads);
            $externalAWB = $result['id'];
            $files = mergePDF($result['link']);
            $shipments = $shipments->map(function ($value, $key) use ($externalAWB, $resource, $files) {
                $value['external_awb'] = $externalAWB[$key];
                $value['resource'] = $resource;
                $value['url'] = $files;
                return $value;
            });
            $links = array_merge($links, $result['link']);

            Shipment::insert($shipments->toArray());
        }

        return $this->response(
            [
                'id' => Shipment::select('id')->first()->id,
                'link' => mergePDF($links)
            ],
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

    public function hook(ShipmentRequest $request)
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
        $carriers = Carriers::where('is_active', true)
            ->where($data['type'], true);

        if ($data['is_cod'])
            $carriers->where('accept_cod', $data['is_cod']);
        $carrier = $carriers->get()->map(function ($carrier) use ($data) {
            if ($data['type'] == 'express')
                $carrier['fees'] = (number_format($this->calculateFees($carrier->id, null, $data['country_code'], $data['type'], $data['weight']), 2));
            else
                $carrier['fees'] = (number_format($this->calculateFees($carrier->id, $data['city_from'], $data['city_to'], $data['type'], $data['weight']), 2));
            return $carrier;



        })->reject(function($carrier){
            return  floatval($carrier['fees']) <= 0;
        });
        return $this->response($carrier->flatten(), 'Fees Calculated Successfully');
    }

    public function template(ShipmentRequest $request)
    {
        $path = storage_path() . '/' . 'app/template/domestic_template.xlsx';
        return $this->download($path);
    }

    public function guestShipment(ShipmentRequest $request)
    {
        $shipment = $request->validated();


        $strip_token = $shipment['strip_token'];

        $countries = Country::pluck('code', 'name_en');
        $merchentInfo = Merchant::findOrFail(1);
        $dom_rates = collect($merchentInfo->domestic_rates);
        $shipment['sender_country'] = $countries[$shipment['sender_country']] ?? null;
        $shipment['status'] = 'DRAFT';
        if ($shipment['type'] == 'express') {
            $shipment['group'] = 'EXP';
            $shipment['consignee_country'] = $countries[$shipment['consignee_country']] ?? null;
            $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_country'], 'express', $shipment['actual_weight']);
        } else {
            $shipment['group'] = 'DOM';
            $shipment['consignee_country'] = $merchentInfo['country_code'];

            $domestic_rates = $dom_rates->where('code', '=', $shipment['sender_city'])->first();
            $shipment['fees'] = $domestic_rates['price'] ?? 0;
            if ($shipment['fees'] == 0)
                throw new InternalException('Domestic Rates Is Zero');
        }


        $shipment['merchant_id'] = 1;
        $shipment['created_by'] = 1;
        $shipment['logs'] = collect([
            [
                'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                'UpdateLocation' => $shipment['consignee_address_description'] ?: '',
                'UpdateDescription' => 'Create Shipment'
            ]
        ]);
        $shipment['created_at'] = Carbon::now();
        $shipment['updated_at'] = Carbon::now();

        unset($shipment['strip_token'], $shipment['type']);
        $provider = Carriers::where('id', $shipment['carrier_id'])->first()->name;
        return $this->createShipmentDB(collect($shipment), $provider);

        // $infoTransaction =   [
        //     'amount' =>  currency_exchange($data['amount'], $merchecntInfo->currency_code, 'USD'),
        //     'currency' => 'USD',
        //     'source' => $data['token'],
        //     'description' => "Merachnt Deposit " . $merchecntInfo->name,
        // ];

        // $this->stripe->InvoiceWithToken($infoTransaction);
    }
}
