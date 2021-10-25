<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class aramex
{
    private static $CREATE_PICKUP_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreatePickup';
    private static $CANCEL_PICKUP_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CancelPickup';
    private static $PRINT_LABEL_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/PrintLabel';
    private static $CREATE_SHIPMENTS_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments';

    private $config;
    function __construct()
    {
        $this->config = [
            'UserName' => config('aramex.ARAMEX_USERNAME'),
            'Password' => config('aramex.ARAMEX_PASSWORD'),
            'AccountNumber' => config('aramex.ARAMEX_ACCOUNT_NUMBER'),
            'AccountPin' => config('aramex.ARAMEX_PIN'),
            'AccountEntity' => config('aramex.ARAMEX_ACCOUNT_ENTITY'),
            'AccountCountryCode' => config('aramex.ARAMEX_ACCOUNT_COUNTRY_CODE'),
            'Version' => config('aramex.VERSION'),
            'Source' => config('aramex.SOURCE')
        ];
    }

    public function createPickup($email,$date,$address)
    {
        $payload = json_decode(Storage::disk('local')->get('template/aramex/pickup.create.json'),true);
        $payload['ClientInfo'] = $this->config;

        $payload['Pickup']['Reference1'] = $address['description'];
        $payload['Pickup']['PickupAddress']['Line1'] = $address['description'];
        $payload['Pickup']['PickupAddress']['Line2'] = $address['area'];
        $payload['Pickup']['PickupAddress']['Line3'] = '';
        $payload['Pickup']['PickupAddress']['City'] = $address['name_en'];
        $payload['Pickup']['PickupAddress']['CountryCode'] = $address['country_code'];

        $payload['Pickup']['PickupContact']['PersonName'] = $address['name_en'];
        $payload['Pickup']['PickupContact']['CompanyName'] = $address['name_en'];
        $payload['Pickup']['PickupContact']['PhoneNumber1'] = $address['phone'];
        $payload['Pickup']['PickupContact']['CellPhone'] = $address['phone'];
        $payload['Pickup']['PickupContact']['EmailAddress'] = $email;

        $payload['Pickup']['PickupLocation'] = $address['city_code'];
        $payload['Pickup']['PickupDate'] = '/Date('.(strtotime($date) * 1000).')/';

        $ReadyTime  = strtotime(Carbon::createFromFormat('Y-m-d',$date)->format('Y-m-d').' 03:00 PM') * 1000;
        $LastPickupTime = $ClosingTime = strtotime(Carbon::createFromFormat('Y-m-d',$date)->format('Y-m-d').' 04:00 PM') * 1000;

        $payload['Pickup']['ReadyTime'] = '/Date('.$ReadyTime.')/';
        $payload['Pickup']['LastPickupTime'] = '/Date('.$LastPickupTime.')/';
        $payload['Pickup']['ClosingTime'] = '/Date('.$ClosingTime.')/';

        $response = Http::post(self::$CREATE_PICKUP_URL, $payload);

        return $response->json();
    }

    public function cancelPickup($pickup_guid)
    {
        $payload = [
           "ClientInfo" => $this->config,
            "Comments" => "",
            "PickupGUID" => $pickup_guid,
            "Transaction" => [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ]
        ];


        $response = Http::post(self::$CANCEL_PICKUP_URL, $payload);

        // if (! $response->successful()) {
        //     throw new AramexException("Aramex Cancel Pickup – Something Went Wrong", 1);
        // }

        // if ($response->json()['HasErrors']) {
        //     throw new AramexException("Cannot cancel pickup please call 06 535 8855", null, null, $response->json()['Notifications']);
        // }

        return $response->json();
    }

    public function printLabel($shipment_number, $ReportID = 9729)
    {
        $payload =  [
            "ClientInfo" => $$this->config,
            "LabelInfo" => [
                "ReportID" => $ReportID,
                "ReportType" => "URL"
            ],
            "OriginEntity" => "AMM",
            "ProductGroup" => "DOM",
            "ShipmentNumber" => $shipment_number,
            "Transaction" => [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ]
        ];

        $response = Http::post(self::$PRINT_LABEL_URL, $payload);

        // if (! $response->successful()) {
        //     throw new AramexException("Aramex Print Label – Something Went Wrong", 1);
        // }

        // if ($response->json()['HasErrors']) {
        //     throw new AramexException("Cannot get label URL", null, null, $response->json()['Notifications']);
        // }

        // return $response->json()['ShipmentLabel']['LabelURL'];
    }

    public function createShipment()
    {
        $payload = json_decode(Storage::disk('local')->get('template/aramex/shipment.create.json'),true);
        $payload['ClientInfo'] = $this->config;

        $payload['Shipments']['Shipper']['Reference1'] = '';// / merchant -> id;
        $payload['Shipments']['Shipper']['AccountNumber'] = ''; //

        $payload['Shipments']['Shipper']['PartyAddress']['line1'] = ''; // address area
        $payload['Shipments']['Shipper']['PartyAddress']['Line2'] = ''; //
        $payload['Shipments']['Shipper']['PartyAddress']['Line3'] = ''; //
        $payload['Shipments']['Shipper']['PartyAddress']['City'] = ''; // address city code ;
        $payload['Shipments']['Shipper']['PartyAddress']['country code'] = ''; // add country code to be merchant-> country code

        $payload['Shipments']['Shipper']['Contact']['PersonName'] = ''; // // address name
        $payload['Shipments']['Shipper']['Contact']['CompanyName'] = ''; // address name
        $payload['Shipments']['Shipper']['Contact']['PhoneNumber1'] = ''; // address phone 
        $payload['Shipments']['Shipper']['Contact']['CellPhone'] = ''; // address phone 
        $payload['Shipments']['Shipper']['Contact']['EmailAddress'] = ''; // merchant email 


        $payload['Shipments']['Consignee']['PartyAddress']['Line1'] = ''; // consignee description 
        $payload['Shipments']['Consignee']['PartyAddress']['Line2'] = ''; // consginee area 
        $payload['Shipments']['Consignee']['PartyAddress']['Line3'] = ''; // consignee second phone 
        $payload['Shipments']['Consignee']['PartyAddress']['City'] = ''; //  consginee city code  
        $payload['Shipments']['Consignee']['PartyAddress']['CountryCode'] = ''; // merch country code  

        $payload['Shipments']['Consignee']['Contact']['PersonName'] = ''; // consginee name
        $payload['Shipments']['Consignee']['Contact']['CompanyName'] = ''; // consignee name 
        $payload['Shipments']['Consignee']['Contact']['PhoneNumber1'] = ''; // consignee phone
        $payload['Shipments']['Consignee']['Contact']['PhoneNumber2'] = ''; // consignee second phone
        $payload['Shipments']['Consignee']['Contact']['CellPhone'] = ''; //
        $payload['Shipments']['Consignee']['Contact']['EmailAddress'] = ''; // remove it and make it static a@a.com

        $payload['Shipments']['ShippingDateTime'] = ''; // timestamp for tomorrow same time of creation
        $payload['Shipments']['DueDate'] = ''; //timestamp for tomorrow same time of creation 
        $payload['Shipments']['Comments'] = ''; // notes 

        $payload['Shipments']['Details']['CashOnDeliveryAmount']['CurrencyCode'] = ''; // merchant currency 
        $payload['Shipments']['Details']['CashOnDeliveryAmount']['Value'] = ''; // cod 

        $payload['Shipments']['Details']['DescriptionOfGoods'] = ''; // content 
        $payload['Shipments']['Details']['NumberOfPieces'] = ''; // picees 
        $payload['Shipments']['Details']['Services'] = ''; // if cod > 0 make the value "CODS" else ""
        $payload['Shipments']['Details']['ProductType'] = 'DOM'; // fixed remove it from here
        dd($payload);

        $response = Http::post(self::$CREATE_SHIPMENTS_URL, $payload);

        // if (! $response->successful()) {
        //     throw new AramexException("Aramex Create Shipments – Something Went Wrong ",null,null, $shipmentPayload);
        // }

        // if ($response->json()['HasErrors']) {
        //     $notifications = $response->json()['Notifications'] ? $response->json()['Notifications'] : $response->json()['Shipments'][0]['Notifications'];
        //     throw new AramexException("Cannot create Aramex shipment", null, null, $notifications);
        // }

        // return $response->json();
    }


}