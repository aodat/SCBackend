<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Exports\ShipmentExport;
use App\Http\Requests\Merchant\ShipmentRequest;
use App\Jobs\ShipmentWebHooks;
use App\Models\Carriers;
use App\Models\Country;
use App\Models\Invoices;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ShipmentController extends MerchantController
{

    private $status = [
        'DRAFT' => 0, 'PROCESSING' => 0, 'COMPLETED' => 0, 'RENTURND' => 0, 'PENDING_PAYMENTS' => 0,
    ];

    public function index(ShipmentRequest $request)
    {
        $shipments = $this->search($request->json()->all());
        $merchant_id = Request()->user()->merchant_id;
        $type = $request->type ?? 'DOM';

        $tabs = DB::table(DB::raw("(select id,CASE WHEN s.status = 'COMPLETED' && s.transaction_id is null THEN 'PENDING_PAYMENTS' ELSE s.status END  as exstatus from shipments s where merchant_id = $merchant_id and `group` = '$type' and is_deleted = false) as subs"))
            ->select('exstatus', DB::raw('count(id) as counter'))
            ->groupByRaw('exstatus')
            ->pluck('counter', 'exstatus');

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
        $shipments = $this->search($request->json()->all())->get();
        $path = "export/shipments-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = exportXLSX(new ShipmentExport($shipments), $path);
        } else {
            $url = exportPDF('shipments', $path, $shipments);
        }

        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }

    private function search($filters)
    {
        $merchant_id = Request()->user()->merchant_id;
        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $external = $filters['external'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $phone = $filters['phone'] ?? [];
        $cod = $filters['cod']['val'] ?? null;
        $operation = $filters['cod']['operation'] ?? null;
        $type = $filters['type'] ?? 'DOM';

        $shipments = DB::table('shipments as s')->join('carriers as car', 'car.id', 's.carrier_id')
            ->where('merchant_id', $merchant_id)
            ->where('is_deleted', false)
            ->whereBetween('s.created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($external)) {
            $shipments->whereIn('s.external_awb', $external);
        }

        if (count($phone)) {
            $shipments = $shipments->where(function ($query) use ($phone) {
                $query->whereIn('s.sender_phone', $phone)->orWhereIn('s.consignee_phone', $phone);
            });
        }

        if ($operation) {
            $shipments->where("s.cod", $operation, $cod);
        } else if ($cod) {
            $shipments->whereBetween('s.cod', [intval($cod), intval($cod) . '.99']);
        }

        $shipments->where('s.group', $type);
        if (count($statuses)) {
            if (in_array('PENDING_PAYMENTS', $statuses)) {
                $shipments->where('s.status', '=', 'COMPLETED')->whereNull('s.transaction_id');
            } else {
                $shipments->whereIn('s.status', $statuses);
            }

        }
        $shipments->orderBy('created_at', 'desc');
        $shipments->select(
            's.id',
            's.created_at',
            's.external_awb',
            's.consignee_name',
            's.consignee_email',
            's.consignee_phone',
            DB::raw('CASE WHEN s.status = \'COMPLETED\' and s.transaction_id is null THEN \'PENDING PAYMENTS\' ELSE s.status END as status'),
            's.status as actual_status',
            's.fees',
            's.url',
            's.consignee_country',
            's.consignee_city',
            's.consignee_area',
            'car.name as provider_name',
            's.sender_name',
            's.consignee_address_description',
            's.cod',
            's.delivered_at',
            's.pieces',
            's.content'
        );

        return $shipments;
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
            $addressList = App::make('merchantAddresses');
            $merchantInfo = App::make('merchantInfo');
            (collect($shipmentRequest)->pluck('sender_address_id'))->map(function ($address_id) use ($merchantInfo, $addressList) {
                if ($addressList->where('id', $address_id)->where('country_code', $merchantInfo->country_code)->isEmpty()) {
                    throw new InternalException('This is not Domestic request the merchant code different with send country code');
                }

            });
            return $this->shipment('DOM', collect($shipmentRequest), 'Aramex');
        });
    }

    private function shipment($type, $shipments, $provider = null)
    {
        $countries = Country::pluck('code', 'name_en');
        $merchentInfo = $this->getMerchentInfo();
        $addresses = collect($merchentInfo->addresses);

        $shipments = $shipments->map(function ($shipment) use ($addresses, $merchentInfo, $type, $countries) {
            $address = $addresses->where('id', '=', $shipment['sender_address_id'])->first();

            if ($address == null) {
                throw new InternalException('Sender address id is in valid');
            }

            if ($merchentInfo->country_code == null) {
                throw new InternalException('Merchent country is empty');
            }

            $shipment['sender_email'] = $merchentInfo['email'];
            $shipment['sender_name'] = $address['name'];
            $shipment['sender_phone'] = $address['phone'];
            $shipment['sender_country'] = $merchentInfo['country_code'];
            $shipment['sender_city'] = $address['city'];
            $shipment['sender_area'] = $address['area'];
            $shipment['sender_address_description'] = $address['description'];

            unset($shipment['sender_address_id']);

            $shipment['group'] = $type;
            $shipment['actual_weight'] = $shipment['actual_weight'] ?? 0.5;
            $shipment['consignee_country'] = $countries[$shipment['consignee_country']] ?? null;
            if ($type == 'DOM') {
                $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_city'], 'domestic', $shipment['actual_weight']);
            } else {
                $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_country'], 'express', $shipment['actual_weight']);
            }

            // Check if COD is Zero OR Shipment Type Express
            // Check and dedact
            if ($type == 'EXP' || $shipment['cod'] == 0) {
                $this->checkbalance($shipment['fees']);
            }

            $shipment['merchant_id'] = Request()->user()->merchant_id;
            $shipment['created_by'] = Request()->user()->id;
            $shipment['status'] = 'DRAFT';
            $shipment['created_at'] = Carbon::now();
            $shipment['updated_at'] = Carbon::now();

            return $shipment;
        });
        return $this->createShipmentDB($shipments, $provider);
    }

    private function checkbalance($fees)
    {
        $merchant = $this->getMerchentInfo();
        if ($fees <= $merchant->bundle_balance || $merchant->payment_type == 'POSTPAID') {
            $merchant->bundle_balance -= $fees;
            $merchant->save();
            return true;
        }
        throw new InternalException('Your bundle balance is not enough to create shipment.', 400);
    }

    private function createShipmentDB($shipments, $provider)
    {
        $resource = Request()->header('agent') ?? 'API';
        $payments = $shipments->sum('payment');
        $fees = $shipments->sum('fees');
        $getbulk = $shipments->where('carrier_id', 1);
        $payloads = $getbulk->map(function ($data) {
            return $this->generateShipmentArray('Aramex', $data);
        });

        $links = [];
        if ($payloads->isEmpty()) { // for signle Shipment Request
            if (isset($shipments->toArray()[0])) {
                $shipment = $shipments->toArray()[0];
            } else {
                $shipment = $shipments->toArray();
            }

            $result = $this->generateShipment($provider, $this->getMerchentInfo(), $shipment);
            $links[] = $result['link'];

            $shipment['external_awb'] = $result['id'];
            $shipment['resource'] = $resource;
            $shipment['url'] = $result['link'];
            shipment::withoutGlobalScope('ancient')->create($shipment);

        } else if (!$payloads->isEmpty()) {
            $result = $this->generateShipment('Aramex', $this->getMerchentInfo(), $payloads);
            $externalAWB = $result['id'];
            $files = $result['link'];

            $shipments = $shipments->map(function ($value, $key) use ($externalAWB, $resource, $files) {
                $value['external_awb'] = $externalAWB[$key];
                $value['resource'] = $resource;
                $value['url'] = $files[$key];

                unset($value['payment']);
                return $value;
            });
            $links = array_merge($links, $result['link']);
            Shipment::insert($shipments->toArray());
        }

        $lastShipment = Shipment::first();

        if ($payments > 0) {
            Invoices::create(
                [
                    "merchant_id" => Request()->user()->merchant_id,
                    "user_id" => Request()->user()->id,
                    "fk_id" => $lastShipment->id,
                    "customer_name" => $lastShipment->consignee_name,
                    "customer_email" => $lastShipment->consignee_email,
                    "description" => $lastShipment->consignee_notes,
                    "amount" => $payments,
                ]
            );
        }

        $merchant = Merchant::findOrFail(Request()->user()->merchant_id);
        Transaction::create([
            "type" => "CASHOUT",
            "subtype" => "BUNDLE",
            "item_id" => $lastShipment->id,
            "created_by" => Request()->user()->id,
            "merchant_id" => Request()->user()->merchant_id,
            "amount" => $fees,
            "status" => "COMPLETED",
            "balance_after" => $merchant->bundle_balance,
            "source" => "SHIPMENT",
        ]);

        return $this->response(
            [
                'id' => $lastShipment->id,
                'link' => mergePDF($links),
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
        $shipmentInfo = Shipment::withoutGlobalScope('ancient')->where('external_awb', $request->WaybillNumber)->first();
        $this->webhook($shipmentInfo, $request->all());
    
        return $this->successful('Webhook Completed');
    }

    public function calculate(ShipmentRequest $request)
    {
        $data = $request->validated();
        $carriers = Carriers::where('is_active', true)
            ->where($data['type'], true);

        if ($data['is_cod']) {
            $carriers->where('accept_cod', $data['is_cod']);
        }

        $carrier = $carriers->get()->map(function ($carrier) use ($data) {
            if ($data['type'] == 'express') {
                $carrier['fees'] = (number_format($this->calculateFees($carrier->id, null, $data['country_code'], $data['type'], $data['weight']), 2));
            } else {
                $carrier['fees'] = (number_format($this->calculateFees($carrier->id, $data['city_from'], $data['city_to'], $data['type'], $data['weight']), 2));
            }

            return $carrier;
        })->reject(function ($carrier) {
            return floatval($carrier['fees']) <= 0;
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
        $type = $shipment['type'];

        unset($shipment['strip_token'], $shipment['type']);

        $shipment['sender_country'] = $countries[$shipment['sender_country']] ?? null;
        $shipment['consignee_country'] = $countries[$shipment['consignee_country']] ?? null;

        $shipment['status'] = 'DRAFT';

        if ($type == 'express') {
            $shipment['group'] = 'EXP';
            $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_country'], 'express', $shipment['actual_weight']);
        } else {
            $shipment['group'] = 'DOM';
            $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_city'], 'domestic', $shipment['actual_weight']);
        }

        $shipment['merchant_id'] = 900;
        $shipment['created_by'] = 900;
        $shipment['shipment_logs'] = collect([
            [
                'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                'UpdateLocation' => $shipment['consignee_address_description'] ?: '',
                'UpdateDescription' => 'Create Shipment',
            ],
        ]);
        $shipment['created_at'] = Carbon::now();
        $shipment['updated_at'] = Carbon::now();

        // if ($type == 'domestic' || $shipment['carrier_id'] == 1) {
        //     $result = $this->createShipmentDB(collect([$shipment]), 'Aramex');
        // } else {
        //     $provider = Carriers::findOrFail($shipment['carrier_id'])->name;
        //     $result = $this->createShipmentDB(collect($shipment), $provider);
        // }

        return $this->successful('Your Shipment Created Successfully');

    }

    public function tracking(ShipmentRequest $request)
    {
        $shipment = Shipment::where('external_awb', $request->shipment_number)->first();
        $details = $this->track($shipment->carrier_name, $request->shipment_number, true);
        return $this->response($details, 'Shipment Info');
    }

    public function delete($id, ShipmentRequest $request)
    {
        $data = Shipment::findOrFail($id);
        if ($data->status != 'DRAFT') {
            $this->error('You Cant Delete This Shipment (Only Draft)');
        }

        $data->is_deleted = true;
        $data->save();

        return $this->successful('Shipment Deleted Successfully');
    }

    public function aramexTracking(ShipmentRequest $request)
    {
        $lists = DB::table('shipments')->where('carrier_id', 1)
            ->where('status', 'PROCESSING')
            ->pluck('external_awb');
        $lists->map(function ($external_awb) {
            $result = collect($this->track('Aramex', [$external_awb], true));
            $result->map(function ($info) use ($external_awb) {
                $shipmentInfo = $info['Value'];
                $new = [];
                foreach ($shipmentInfo as $key => $value) {
                    $time = get_string_between($value['UpdateDateTime'], '/Date(', '+0200)/') / 1000;
                    $new[] = [
                        'UpdateDateTime' => Carbon::parse($time)->format('Y-m-d H:i:s'),
                        'UpdateLocation' => $value['UpdateLocation'],
                        'UpdateDescription' => $value['Comments'],
                        'TrackingDescription' => $value['UpdateDescription'],
                    ];
                }
                Shipment::withoutGlobalScope('ancient')->where('external_awb', $external_awb)->update(['shipment_logs' => collect($new)]);
            });
        });

        return $this->successful('Track Shipment Completed Sucessfully');
    }
}
