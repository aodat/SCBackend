<?php

namespace Libs;

use App\Exceptions\CarriersException;
use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\Utilities\AWSServices;
use App\Http\Controllers\Utilities\Shipcash;
use App\Http\Requests\Carrier\AramexRequest;
use App\Models\City;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Traits\ResponseHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Aramex
{
    use ResponseHandler;

    private static $CREATE_PICKUP_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreatePickup';
    private static $CANCEL_PICKUP_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CancelPickup';
    private static $PRINT_LABEL_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/PrintLabel';
    private static $CREATE_SHIPMENTS_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments';
    private static $TRACK_SHIPMENTS_URL = 'https://ws.aramex.net/ShippingAPI.V2/Tracking/Service_1_0.svc/json/TrackShipments';

    private $config, $setup;
    public function __construct($settings = null)
    {
        $this->config = [
            'UserName' => $settings['aramex_username'] ?? config('carriers.aramex.USERNAME'),
            'Password' => $settings['aramex_password'] ?? config('carriers.aramex.PASSWORD'),
            'AccountNumber' => $settings['aramex_account_number'] ?? config('carriers.aramex.ACCOUNT_NUMBER'),
            'AccountPin' => $settings['aramex_pin'] ?? config('carriers.aramex.PIN'),
            'AccountEntity' => $settings['aramex_account_entity'] ?? config('carriers.aramex.ACCOUNT_ENTITY'),
            'AccountCountryCode' => $settings['aramex_account_country_code'] ?? config('carriers.aramex.ACCOUNT_COUNTRY_CODE'),
            'Version' => $settings['aramex_version'] ?? config('carriers.aramex.VERSION'),
            'Source' => $settings['aramex_source'] ?? config('carriers.aramex.SOURCE'),
        ];

        $this->setup = [
            'Sh014' => ['status' => 'DRAFT', 'delivered_at' => null, 'returned_at' => null, 'paid_at' => null],
            'SH005' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null, 'actions' => ['check_chargable_weight']],
            'SH006' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null, 'actions' => ['check_chargable_weight']],
            'SH069' => ['status' => 'RENTURND', 'returned_at' => Carbon::now(), 'delivered_at' => null, 'paid_at' => null],
            'SH239' => ['status' => 'COMPLETED', 'paid_at' => Carbon::now(), 'delivered_at' => Carbon::now(), 'returned_at' => null, 'actions' => ['create_transaction', 'update_merchant_balance']],
        ];
    }

    public function validate($merchentInfo)
    {
        $shipmentInfo = [
            "sender_email" => "test@shipcash.net",
            "sender_name" => "Shipcash Test - Sender",
            "sender_phone" => "012345678",
            "sender_country" => "Jordan",
            "sender_city" => "Amman",
            "sender_area" => "Amman",
            "sender_address_description" => "Amman - 1st Cricle",
            "consignee_name" => "Shipcash Test - Consignee",
            "consignee_email" => "test@shipcash.net",
            "consignee_phone" => "123456789",
            "consignee_country" => "GB",
            "consignee_city" => "England",
            "consignee_area" => "ALL",
            "consignee_zip_code" => "CR5 3FT",
            "consignee_address_description" => "13 DICKENS DR",
            "content" => "Test Content",
            "pieces" => 1,
            "actual_weight" => 1,
            "declared_value" => 1,
            "is_doc" => true,
            "group" => "EXP",
        ];

        $shipment = $this->shipmentArray($merchentInfo, $shipmentInfo);
        return $this->createShipment(null, $shipment, true);

    }

    public function createPickup($email, $date, $address)
    {
        $payload = $this->bindJsonFile('pickup.create.json');
        $payload['ClientInfo'] = $this->config;

        $payload['Pickup']['Reference1'] = $address['description'];
        $payload['Pickup']['PickupAddress']['Line1'] = $address['description'];
        $payload['Pickup']['PickupAddress']['Line2'] = $address['area'];
        $payload['Pickup']['PickupAddress']['Line3'] = '';
        $payload['Pickup']['PickupAddress']['City'] = $address['city'];
        $payload['Pickup']['PickupAddress']['CountryCode'] = $address['country_code'];

        $payload['Pickup']['PickupContact']['PersonName'] = $address['name'];
        $payload['Pickup']['PickupContact']['CompanyName'] = $address['name'];
        $payload['Pickup']['PickupContact']['PhoneNumber1'] = $address['phone'];
        $payload['Pickup']['PickupContact']['CellPhone'] = $address['phone'];
        $payload['Pickup']['PickupContact']['EmailAddress'] = $email;

        $payload['Pickup']['PickupLocation'] = $address['city_code'] ?? $address['city'];
        $payload['Pickup']['PickupDate'] = '/Date(' . (strtotime($date) * 1000) . ')/';

        $ReadyTime = strtotime(Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d') . ' 03:00 PM') * 1000;
        $LastPickupTime = $ClosingTime = strtotime(Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d') . ' 04:00 PM') * 1000;

        $payload['Pickup']['ReadyTime'] = '/Date(' . $ReadyTime . ')/';
        $payload['Pickup']['LastPickupTime'] = '/Date(' . $LastPickupTime . ')/';
        $payload['Pickup']['ClosingTime'] = '/Date(' . $ClosingTime . ')/';

        $response = Http::post(self::$CREATE_PICKUP_URL, $payload);
        if (!$response->successful()) {
            throw new CarriersException('Aramex Create Pickup – Something Went Wrong', $payload, $response->json());
        }

        if ($response->json()['HasErrors']) {
            throw new CarriersException('Aramex Data Provided Not Correct - Create Pickup', $payload, $response->json());
        }

        $final = $response->json();
        return ['id' => $final['ProcessedPickup']['ID'], 'guid' => $final['ProcessedPickup']['GUID']];
    }

    public function cancelPickup($pickupInfo)
    {
        $payload = ["ClientInfo" => $this->config, "PickupGUID" => $pickupInfo->hash];
        $response = Http::post(self::$CANCEL_PICKUP_URL, $payload);
        if (!$response->successful()) {
            throw new CarriersException('Aramex Cancel Pickup – Something Went Wrong', $payload, $response->json());
        }

        if ($response->json()['HasErrors']) {
            throw new CarriersException('Aramex Data Provided Not Correct', $payload, $response->json());
        }

        return true;
    }

    public function printLabel($shipments, $ReportID = 9729)
    {
        $payload = [
            "ClientInfo" => $this->config,
            "LabelInfo" => ["ReportID" => $ReportID, "ReportType" => "URL"],
        ];
        $files = [];
        foreach ($shipments as $shipment) {
            $payload['ShipmentNumber'] = $shipment;

            $response = Http::post(self::$PRINT_LABEL_URL, $payload);
            if (!$response->successful()) {
                throw new CarriersException('Aramex Print Label – Something Went Wrong', $payload, $response->json());
            }

            if ($response->json()['HasErrors']) {
                throw new CarriersException('Aramex Data Provided Not Correct', $payload, $response->json());
            }

            $files[] = $response->json()['ShipmentLabel']['LabelURL'];
        }
        return $files;
    }

    public function createShipment($merchentInfo = null, $shipmentInfo, $checkAuth = false)
    {
        $payload = [
            'ClientInfo' => $this->config,
            'LabelInfo' => ['ReportID' => 9729, 'ReportType' => 'URL'],
            'Shipments' => $shipmentInfo,
            'Transaction' => [
                'Reference1' => '',
                'Reference2' => '',
                'Reference3' => '',
                'Reference4' => '',
                'Reference5' => '',
            ],
        ];

        $response = Http::post(self::$CREATE_SHIPMENTS_URL, $payload);

        if ($checkAuth) {
            return $response->successful();
        }

        if (!$response->successful()) {
            throw new CarriersException('Aramex Create Shipment – Something Went Wrong', $payload, $response->json());
        }

        if ($response->json()['HasErrors']) {
            throw new CarriersException('Aramex Data Provided Not Correct - Create Shipment', $payload, $response->json());
        }

        $result = [];
        foreach ($response->json()['Shipments'] as $ship) {
            $result[] = [
                'id' => $ship['ID'],
                'file' => AWSServices::uploadToS3('aramex/shipment', file_get_contents($ship['ShipmentLabel']['LabelURL']), 'pdf', true),
            ];
        }
        return $result;
    }

    public function shipmentArray($merchentInfo, $shipmentInfo)
    {
        $data = $this->bindJsonFile('shipment.create.json');

        $data['Shipper']['Reference1'] = $merchentInfo->id;
        $data['Shipper']['AccountNumber'] =
        $data['Consignee']['AccountNumber'] =
        $this->config['AccountNumber'];

        $data['Shipper']['PartyAddress']['Line1'] = $shipmentInfo['sender_address_description'];
        $data['Shipper']['PartyAddress']['Line2'] = $shipmentInfo['sender_area'];
        $data['Shipper']['PartyAddress']['City'] = ucfirst(strtolower($shipmentInfo['sender_city']));
        $data['Shipper']['PartyAddress']['CountryCode'] = $merchentInfo->country_code;
        $data['Shipper']['Contact']['PersonName'] = $shipmentInfo['sender_name'];
        $data['Shipper']['Contact']['CompanyName'] = $shipmentInfo['sender_name']; // $merchentInfo->name;
        $data['Shipper']['Contact']['PhoneNumber1'] = $shipmentInfo['sender_phone'];
        $data['Shipper']['Contact']['CellPhone'] = $shipmentInfo['sender_phone'];

        $data['Consignee']['PartyAddress']['Line1'] = $shipmentInfo['consignee_address_description'];
        $data['Consignee']['PartyAddress']['Line2'] = $shipmentInfo['consignee_second_phone'] ?? '';
        $data['Consignee']['PartyAddress']['Line3'] = '';
        $data['Consignee']['PartyAddress']['City'] = $shipmentInfo['consignee_city'];
        $data['Consignee']['PartyAddress']['StateOrProvinceCode'] = City::where('name_en', $shipmentInfo['consignee_city'])->first() ? City::where('name_en', $shipmentInfo['consignee_city'])->first()->code : '';

        if ($shipmentInfo['group'] == 'EXP') {
            $data['Consignee']['PartyAddress']['PostCode'] = $shipmentInfo['consignee_zip_code'] ?? '';
        }

        $data['Consignee']['PartyAddress']['CountryCode'] = ($shipmentInfo['group'] == 'DOM') ? $merchentInfo->country_code : $shipmentInfo['consignee_country'];

        $data['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name'];
        $data['Consignee']['Contact']['CompanyName'] = $shipmentInfo['consignee_name'];
        $data['Consignee']['Contact']['PhoneNumber1'] = $shipmentInfo['consignee_phone'];
        $data['Consignee']['Contact']['PhoneNumber2'] = $shipmentInfo['consignee_second_phone'] ?? '';
        $data['Consignee']['Contact']['CellPhone'] = $shipmentInfo['consignee_phone'];

        $data['ShippingDateTime'] = '/Date(' . (Carbon::tomorrow()->getTimestamp() * 1000) . ')/';
        $data['DueDate'] = '/Date(' . (Carbon::tomorrow()->getTimestamp() * 1000) . ')/';
        $data['Comments'] = $shipmentInfo['consignee_notes'] ?? '';

        $data['Details']['DescriptionOfGoods'] = $shipmentInfo['content'];
        $data['Details']['GoodsOriginCountry'] = $merchentInfo->country_code;
        $data['Details']['NumberOfPieces'] = $shipmentInfo['pieces'];
        $data['Details']['ProductGroup'] = $shipmentInfo['group'];

        $data['Details']['ActualWeight']['Value'] = $shipmentInfo['actual_weight'] ?? 1;

        if ($shipmentInfo['group'] == 'EXP') {

            $data['Details']['ProductType'] = $shipmentInfo['is_doc'] ? 'PDX' : 'PPX';
        }

        if (isset($shipmentInfo['cod'])) {
            $data['Details']['CashOnDeliveryAmount'] = [
                'CurrencyCode' => ($shipmentInfo['group'] == 'DOM') ? $merchentInfo->currency_code : 'USD',
                "Value" => ($shipmentInfo['group'] == 'DOM') ? $shipmentInfo['cod'] : Shipcash::exchange($shipmentInfo['cod'], $merchentInfo->currency_code),
            ];
        }

        $data['Details']['CashAdditionalAmount'] = [
            'CurrencyCode' => ($shipmentInfo['group'] == 'DOM') ? $merchentInfo->currency_code : 'USD',
            "Value" => "0",
        ];

        $data['Details']['CustomsValueAmount'] = [
            'CurrencyCode' => ($shipmentInfo['group'] == 'DOM') ? $merchentInfo->currency_code : 'USD',
            "Value" => ($shipmentInfo['group'] == 'DOM') ? $shipmentInfo['cod'] : Shipcash::exchange($shipmentInfo['declared_value'], $merchentInfo->currency_code),
        ];

        $data['Details']['CustomsValueAmount']['CurrencyCode'] =
        ($shipmentInfo['group'] == 'DOM') ? $merchentInfo->currency_code : 'USD';

        $data['Details']['Services'] = (isset($shipmentInfo['cod']) && $shipmentInfo['cod'] > 0) ? 'CODS' : '';
        return $data;
    }

    public function trackShipment($shipment_waybills, $all_event = false)
    {
        $awb = [];
        if (!is_array($shipment_waybills)) {
            $awb[] = $shipment_waybills;
        } else {
            $awb = $shipment_waybills;
        }

        $trackingPayload = ["ClientInfo" => $this->config, "Shipments" => $awb];

        $response = Http::post(self::$TRACK_SHIPMENTS_URL, $trackingPayload);
        if (!$response->successful()) {
            throw new CarriersException('Aramex Track Shipments – Something Went Wrong', $trackingPayload, $response->json());
        }

        if ($response->json()['HasErrors']) {
            throw new CarriersException('Cannot track Aramex shipment', $trackingPayload, $response->json());
        }

        $result = $response->json()['TrackingResults'];
        if (empty($result)) {
            throw new CarriersException('Tracking Details Is Empty', $trackingPayload, $response->json());
        }

        if ($all_event) {
            return $result;
        } else if (count($awb) == 1) {
            return last($response['TrackingResults'][0]['Value']);
        }

        return $result;
    }

    public function webhook(AramexRequest $request)
    {
        $data = $request->all();
        $shipmentInfo = Shipment::withoutGlobalScope('ancient')->where('external_awb', $request->WaybillNumber)->first();

        $updated = $this->setup[$data['UpdateCode']] ?? ['status' => 'PROCESSING'];
        $merchant = Merchant::findOrFail($shipmentInfo['merchant_id']);

        $actions = $updated['actions'] ?? [];
        if (isset($updated['actions'])) {
            unset($updated['actions']);
        }

        if (!empty($actions)) {
            $details = $this->trackShipment($shipmentInfo['external_awb']) ?? null;
            $fees = (new ShipmentController)->calculateFees(
                $shipmentInfo['carrier_id'],
                null,
                ($shipmentInfo['group'] == 'DOM') ? $shipmentInfo['consignee_city'] : $shipmentInfo['consignee_country'],
                $shipmentInfo['group'],
                $details['ChargeableWeight']
            );
        }

        foreach ($actions as $action) {
            if ($action == 'create_transaction') {
                $transaction = Transaction::create(
                    [
                        'type' => 'CASHIN',
                        'subtype' => 'COD',
                        'item_id' => $shipmentInfo['id'],
                        'merchant_id' => $shipmentInfo['merchant_id'],
                        'source' => 'SHIPMENT',
                        'status' => 'PROCESSING',
                        'created_by' => $shipmentInfo['created_by'],
                        'balance_after' => ($shipmentInfo['cod'] - $shipmentInfo['fees']) + $merchant->bundle_balance,
                        'amount' => ($shipmentInfo['cod'] - $shipmentInfo['fees']),
                        'resource' => 'API',
                    ]
                );
                $updated['transaction_id'] = $transaction->id;
            } else if ($action == 'update_merchant_balance') {
                if ($shipmentInfo['cod'] > 0) {

                    if (isset($data['Comment2'])) {
                        if (!Str::contains($data['Comment2'], 'Cheque')) {
                            $merchant->cod_balance += $shipmentInfo['cod'];
                        }

                    }

                    $merchant->bundle_balance -= $fees;
                    $merchant->save();
                }
            } else if ($action == 'check_chargable_weight') {
                $updated['chargable_weight'] = $details['ChargeableWeight'];
                if ($shipmentInfo['actual_weight'] <= $updated['chargable_weight']) {

                    $updated['fees'] = $fees;

                    $logs = collect($shipmentInfo->admin_logs);

                    $updated['admin_logs'] = $logs->merge([[
                        'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'UpdateLocation' => '',
                        'UpdateDescription' => 'Update Shipment Weight From ' . $shipmentInfo['actual_weight'] . ' To ' . $updated['chargable_weight'],

                    ]]);
                }
            }
        }

        $shipmentInfo->update($updated);
        return $this->successful('Webhook Completed');

    }

    public function bindJsonFile($file)
    {
        return json_decode(file_get_contents(app_path() . '/Libs/Aramex/' . $file), true);
    }
}
