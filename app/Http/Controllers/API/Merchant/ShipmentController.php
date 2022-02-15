<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Exports\ShipmentExport;
use App\Http\Controllers\Utilities\Documents;
use App\Http\Requests\Merchant\ShipmentRequest;
use App\Models\Carriers;
use App\Models\Country;
use App\Models\Invoices;
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
            ->union(
                DB::table('shipments')
                    ->where('merchant_id', $merchant_id)
                    ->where('group', $type)
                    ->where('is_deleted', false)
                    ->select('status', DB::raw('count(id) as counter'))
                    ->groupBy('status')
            )->pluck('counter', 'exstatus');

        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($shipments->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
    }

    public function show($id, ShipmentRequest $request)
    {
        $data = Shipment::where('id', $id)->orWhere('external_awb', $id)->first();
        return $this->response($data, 'Data Retrieved Sucessfully');
    }

    public function export($type, ShipmentRequest $request)
    {
        $merchentID = Request()->user()->merchant_id;
        $shipments = $this->search($request->json()->all())->get();
        $path = "export/shipments-$merchentID-" . Carbon::today()->format('Y-m-d') . ".$type";

        if ($type == 'xlsx') {
            $url = Documents::xlsx(new ShipmentExport($shipments), $path);
        } else {
            $url = Documents::pdf('shipments', $path, $shipments);
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
            $shipments->where(function ($where) use ($external) {
                foreach ($external as $ext) {
                    $where->orWhere('s.external_awb', 'like', '%' . $ext . '%');
                }
            });
        }

        if (count($phone)) {
            $shipments->where(function ($where) use ($phone) {
                foreach ($phone as $ext) {
                    $where->where(function ($sub) use ($ext) {
                        $sub->orWhere('s.sender_phone', 'like', '%' . $ext . '%')->orWhere('s.consignee_phone', 'like', '%' . $ext . '%');
                    });
                }
            });
        }

        if ($operation) {
            $shipments->where("s.cod", $operation, $cod);
        } else if ($cod) {
            $shipments->whereBetween('s.cod', [intval($cod), intval($cod) . '.99']);
        }

        $shipments->where('s.group', $type);
        $colStatus = 's.status';
        if (count($statuses)) {
            if (in_array('PENDING_PAYMENTS', $statuses)) {
                $shipments->where('s.status', '=', 'COMPLETED')->whereNull('s.transaction_id');
                $colStatus = DB::raw('CASE WHEN s.status = \'COMPLETED\' and s.transaction_id is null THEN \'PENDING PAYMENTS\' ELSE s.status END as status');
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
            $colStatus,
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
            's.content',
            's.last_update'
        );

        return $shipments;
    }

    // Create Express Shipment will be one by one only
    public function createExpressShipment(ShipmentRequest $request)
    {
        $merchantInfo = App::make('merchantInfo');
        if (!$merchantInfo->is_exp_enabled) {
            return $this->error('Create Express Shipment Not Allowed, Please Contact Administrator');
        }

        return DB::transaction(function () use ($request) {
            $shipmentRequest = $request->validated();
            $provider = Carriers::where('id', $shipmentRequest['carrier_id'])->first()->name;
            return $this->shipment('EXP', collect([$shipmentRequest]), $provider);
        });
    }

    public function createDomesticShipment(ShipmentRequest $request)
    {

        $addressList = App::make('merchantAddresses');
        $merchantInfo = App::make('merchantInfo');
        if (!$merchantInfo->is_dom_enabled) {
            return $this->error('Create Domestic Shipment Not Allowed, Please Contact Administrator');
        }

        return DB::transaction(function () use ($request, $merchantInfo, $addressList) {
            $shipmentRequest = $request->validated();
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

            if ($type == 'DOM' && $shipment['cod'] == 0) {
                if (!$merchentInfo->is_cod_enabled) {
                    throw new InternalException('Create Domestic Shipment With No COD Amount Not Allowed, Please Contact Administrator', 400);
                }
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
            $shipment['consignee_notes'] = $shipment['consignee_notes'] ?? '';
            $shipment['consignee_second_phone'] = $shipment['consignee_second_phone'] ?? null;
            $shipment['reference1'] = $shipment['reference'] ?? null;

            if (isset($shipment['reference'])) {
                unset($shipment['reference']);
            }

            $shipment['consignee_country'] = $countries[$shipment['consignee_country']] ?? null;
            if ($type == 'DOM') {
                $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_city'], 'domestic', $shipment['actual_weight']);
            } else {
                $shipment['fees'] = $this->calculateFees($shipment['carrier_id'], null, $shipment['consignee_country'], 'express', $shipment['actual_weight']);
            }

            // Check if COD is Zero OR Shipment Type Express
            // Check and dedact
            $fees = $shipment['fees'];
            if ($merchentInfo->payment_type == 'PREPAID') {
                if ($fees <= $merchentInfo->bundle_balance) {
                    $merchentInfo->bundle_balance -= $fees;
                    $merchentInfo->save();
                } else {
                    throw new InternalException('Your bundle balance is not enough to create shipment.', 400);
                }
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

    private function createShipmentDB($shipments, $provider)
    {
        $resource = Request()->header('agent') ?? 'WEB';
        $payments = $shipments->sum('payment');
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

            $address2 = '';
            if (isset($shipment['consignee_address_description_2'])) {
                $address2 = $shipment['consignee_address_description_2'];
                unset($shipment['consignee_address_description_2']);
            }
            $shipment['consignee_address_description'] = $shipment['consignee_address_description_1'] . ' ' . $address2;

            if (isset($shipment['consignee_address_description_1'])) {
                unset($shipment['consignee_address_description_1']);
            }
            shipment::withoutGlobalScope('ancient')->create($shipment);
        } else if (!$payloads->isEmpty()) {
            $result = $this->generateShipment('Aramex', $this->getMerchentInfo(), $payloads);
            $externalAWB = $result['id'];
            $files = $result['link'];

            $shipments = $shipments->map(function ($value, $key) use ($externalAWB, $resource, $files) {
                $value['external_awb'] = $externalAWB[$key];
                $value['resource'] = $resource;
                $value['url'] = $files[$key];

                $address2 = '';
                if (isset($value['consignee_address_description_2'])) {
                    $address2 = $value['consignee_address_description_2'];
                    unset($value['consignee_address_description_2']);
                }

                $value['consignee_address_description'] = $value['consignee_address_description_1'] . ' ' . $address2;

                if (isset($value['consignee_address_description_1'])) {
                    unset($value['consignee_address_description_1']);
                }

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
        return $this->response(
            [
                'id' => $lastShipment->id,
                'link' => Documents::merge($links),
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

        $shipment['merchant_id'] = 870;
        $shipment['created_by'] = 1632;
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

    public function calculateFees($carrier_id, $from = null, $to, $type, $weight, $merchant_id = null)
    {
        $merchentInfo = $this->getMerchentInfo($merchant_id);
        $to = str_replace("'", "", $to);
        if ($type == 'domestic' || $type == 'DOM') {

            if (!isset($merchentInfo['domestic_rates'][$carrier_id])) {
                throw new InternalException('The Carrier ID ' . $carrier_id . ' No Support domestic , Please Contact Administrators');
            }

            $data = array_map(function ($value) {
                return str_replace("'", "", $value);
            }, $merchentInfo['domestic_rates'][$carrier_id]);

            $rate = collect($data)->where('code', $to);

            if ($rate->isEmpty()) {
                throw new InternalException('Country Code Not Exists, Please Contact Administrators');
            }

            $price = $rate->first()['price'];
            $extra = $rate->first()['additional'] ?? 1.5;

            $fees = 0;
            if ($weight > 0) {
                $weights_count = ceil($weight / 10);
                $weight_fees = (($weights_count - 1) * $extra) + $price;
                $fees += $weight_fees;
            }
            return $fees;
        } else {
            $express_rates = collect(Country::where('code', $merchentInfo['country_code'])->first());
            if ($express_rates->isEmpty()) {
                throw new InternalException('Country Code Not Exists, Please Contact Administrators');
            }

            $express_rates = $express_rates['rates'];
            if (count($express_rates) == 0) {
                throw new InternalException('No Setup Added To This Country, Please Contact Administrators');
            }

            if (!isset($express_rates[$to])) {
                throw new InternalException('No Setup Added To This Country, Please Contact Administrators');
            }

            $rates = collect($express_rates[$to]);
            $zones = $rates->where('carrier_id', $carrier_id);

            if ($zones->count() > 1) {
                throw new InternalException('Somthing Wrong On Rates Setup, Please Contact Administrators');
            }

            if (!isset($zones->first()['zone_id'])) {
                throw new InternalException('Somthing Wrong On Zone ID Setup, Please Contact Administrators');
            }

            $zone_id = $zones->first()['zone_id'];
            $discounts = $merchentInfo['express_rates'][$carrier_id]['discounts'] ?? [];

            $zoneRates = collect($merchentInfo['express_rates'][$carrier_id]['zones'])->where('id', $zone_id);
            if ($zoneRates->count() > 1) {
                throw new InternalException('Express Rates Json Retrun More Than One Zone In User Merchant ID');
            }

            $zoneRates = $zoneRates->first();
            if ($zoneRates == null) {
                return 0;
            }

            $base = $zoneRates['basic'];
            $additional = $zoneRates['additional'];
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if (eval("return " . $weight . $value['condintion'] . $value['weight'] . ";")) {
                        $additional = $additional - ($additional * $value['percent']);
                    }

                }
            }

            $fees = 0;
            if ($weight > 0) {
                $weights_count = ceil($weight / 0.5);
                $weight_fees = (($weights_count - 1) * $additional) + $base;
                $fees += $weight_fees;
            }
        }

        if ($fees == 0) {
            throw new InternalException('Fees Equal Zero');
        }

        return $fees;
    }

}
