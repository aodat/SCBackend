<?php
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
            'aramex_username' => config('aramex.ARAMEX_USERNAME'),
            'aramex_password' => config('aramex.ARAMEX_PASSWORD'),
            'aramex_account_number' => config('aramex.ARAMEX_ACCOUNT_NUMBER'),
            'aramex_pin' => config('aramex.ARAMEX_PIN'),
            'aramex_account_entity' => config('aramex.ARAMEX_ACCOUNT_ENTITY'),
            'aramex_account_country_code' => config('aramex.ARAMEX_ACCOUNT_COUNTRY_CODE'),
            'version' => config('aramex.VERSION'),
            'source' => config('aramex.SOURCE')
        ];
    }

    public function createPickup($address)
    {
        $payload = json_decode(Storage::disk('local')->get('template/aramex/pickup.create.json'),true);
        $payload['ClientInfo'] = $this->config;

        $payload['Pickup']['PickupAddress']['Line1'] = $address->country;
        $payload['Pickup']['PickupAddress']['Line2'] = '';
        $payload['Pickup']['PickupAddress']['Line3'] = '';
        $payload['Pickup']['PickupAddress']['City'] = $address->city;
        
        $payload['Pickup']['PickupContact']['PersonName'] = $address->name;
        $payload['Pickup']['PickupContact']['CompanyName'] = $data['name'] ?? '';
        $payload['Pickup']['PickupContact']['PhoneNumber1'] = $data['phone'] ?? '';
        $payload['Pickup']['PickupContact']['CellPhone'] = '';
        $payload['Pickup']['PickupContact']['EmailAddress'] = $data['email'] ?? '';

        $payload['Pickup']['PickupLocation'] = '';
        $payload['Pickup']['PickupDate'] = '';
        $payload['Pickup']['ReadyTime'] = '';
        $payload['Pickup']['LastPickupTime'] = '';
        $payload['Pickup']['ClosingTime'] = '';
        $payload['Pickup']['Reference1'] = '';
        $payload['Pickup']['PickupItems']['NumberOfPieces'] = 1;
        $payload['Pickup']['PickupItems']['Comments'] = '';

        
        $response = Http::post(self::$CREATE_PICKUP_URL, $payload);
        // dd($response);
        // if (! $response->successful()) {
        //     dd($response->successful());
        //     // throw new AramexException("Aramex Create Pickup – Something Went Wrong", 1);
        // }

        // if ($response->json()['HasErrors']) {
        //     dd($response->json()['HasErrors']);
        //     // throw new AramexException("Cannot create pickup please call 06 535 8855", null, null, $response->json()['Notifications']);

        // }

        // return $response->json();
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
        $payload['Shipments']['Shipper']['Reference1'] = '';
        $payload['Shipments']['Shipper']['AccountNumber'] = '';

        $payload['Shipments']['Shipper']['PartyAddress']['line1'] = '';
        $payload['Shipments']['Shipper']['PartyAddress']['Line2'] = '';
        $payload['Shipments']['Shipper']['PartyAddress']['Line3'] = '';
        $payload['Shipments']['Shipper']['PartyAddress']['City'] = '';

        $payload['Shipments']['Shipper']['Contact']['PersonName'] = '';
        $payload['Shipments']['Shipper']['Contact']['CompanyName'] = '';
        $payload['Shipments']['Shipper']['Contact']['PhoneNumber1'] = '';
        $payload['Shipments']['Shipper']['Contact']['CellPhone'] = '';
        $payload['Shipments']['Shipper']['Contact']['EmailAddress'] = '';

        $payload['Shipments']['ThirdParty']['AccountNumber'] = '';

        $payload['Shipments']['ThirdParty']['PartyAddress']['Line1'] = '';
        $payload['Shipments']['ThirdParty']['PartyAddress']['Line2'] = '';
        $payload['Shipments']['ThirdParty']['PartyAddress']['Line3'] = '';
        $payload['Shipments']['ThirdParty']['PartyAddress']['City'] = '';


        $payload['Shipments']['ThirdParty']['Contact']['PersonName'] = '';
        $payload['Shipments']['ThirdParty']['Contact']['CompanyName'] = '';
        $payload['Shipments']['ThirdParty']['Contact']['PhoneNumber1'] = '';
        $payload['Shipments']['ThirdParty']['Contact']['CellPhone'] = '';
        $payload['Shipments']['ThirdParty']['Contact']['EmailAddress'] = '';


        $payload['Shipments']['Consignee']['PartyAddress']['Line1'] = '';
        $payload['Shipments']['Consignee']['PartyAddress']['Line2'] = '';
        $payload['Shipments']['Consignee']['PartyAddress']['Line3'] = '';
        $payload['Shipments']['Consignee']['PartyAddress']['City'] = '';
        $payload['Shipments']['Consignee']['PartyAddress']['CountryCode'] = '';

        $payload['Shipments']['Consignee']['Contact']['PersonName'] = '';
        $payload['Shipments']['Consignee']['Contact']['CompanyName'] = '';
        $payload['Shipments']['Consignee']['Contact']['PhoneNumber1'] = '';
        $payload['Shipments']['Consignee']['Contact']['PhoneNumber2'] = '';
        $payload['Shipments']['Consignee']['Contact']['CellPhone'] = '';
        $payload['Shipments']['Consignee']['Contact']['EmailAddress'] = '';

        $payload['Shipments']['ShippingDateTime'] = '';
        $payload['Shipments']['DueDate'] = '';
        $payload['Shipments']['Comments'] = '';

        $payload['Shipments']['Details']['Value'] = '';
        $payload['Shipments']['Details']['DescriptionOfGoods'] = '';
        $payload['Shipments']['Details']['NumberOfPieces'] = '';
        $payload['Shipments']['Details']['ProductType'] = '';

        $payload['Shipments']['Details']['CashOnDeliveryAmount'] = [
            "CurrencyCode" => '',
            "Value" => ''
        ];

        $payload['Shipments']['Details']['CashAdditionalAmount']['CurrencyCode'] = '';
        $payload['Shipments']['Details']['Services'] = '';
        $payload['Shipments']['Details']['CustomsValueAmount'] = [
            "CurrencyCode" => '',
            "Value" => ''
        ];
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