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
            "PickupGUID" => $pickup_guid,
        ];
        $response = Http::post(self::$CANCEL_PICKUP_URL, $payload);
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
            "ShipmentNumber" => $shipment_number,
        ];

        $response = Http::post(self::$PRINT_LABEL_URL, $payload);
        return $response->json();
    }

    public function createShipment($merchentInfo,$address,$shipmentInfo)
    {
        // dd($address);
        // $payload = json_decode(Storage::disk('local')->get('template/aramex/shipment.create.json'),true);
        /*v = [
            "ClientInfo" => $this->config,

            "LabelInfo" => [
                "ReportID" => 9729,
                "ReportType" => "URL"
            ],

            "Shipments" => [
                [
                    "Reference1" => "ShipCash",
                    "Reference2" => "",
                    "Reference3" => "",

                    "Shipper" => [
                        "Reference1" => $merchentInfo->id,
                        "Reference2" => "",
                        "AccountNumber" => $this->config['AccountNumber'] ?? '',// this 
                        "PartyAddress" => [
                            "Line1" => "soso",//  $address['name_en'],
                            "Line2" => '',
                            "Line3" => '',
                            "City" => "Amman",//$address['city_code'], // this
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => $merchentInfo->country_code,
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => null,
                            "BuildingName" => null,
                            "Floor" => null,
                            "Apartment" => null,
                            "POBox" => null,
                            "Description" => ""
                        ],

                        "Contact" => [
                            "Department" => "",
                            "PersonName" => $address['name_en'],
                            "Title" => "",
                            "CompanyName" => $address['name_en'],
                            "PhoneNumber1" => $address['phone'],
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => $address['phone'],
                            "EmailAddress" => $merchentInfo->email ?? 'info@aramex.com',
                            "Type" => "",
                        ]
                    ],            
                    "ThirdParty"=> [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => $this->config['AccountNumber'] ?? '',
                        "PartyAddress" => [
                        "Line1" => $shipmentInfo['consignee_address_description'],
                        "Line2" => $shipmentInfo['consignee_area'],
                        "Line3" => $shipmentInfo['consignee_second_phone'],
                        "City" => $shipmentInfo['consignee_city'],
                        "StateOrProvinceCode" => "",
                        "PostCode" => "",
                        "CountryCode" => $merchentInfo->country_code,
                        "Longitude" => 0,
                        "Latitude" => 0,
                        "BuildingNumber" => null,
                        "BuildingName" => null,
                        "Floor" => null,
                        "Apartment" => null,
                        "POBox" => null,
                        "Description" => null                   
                        ],
                        "Contact" => [
                            "Department" => "",
                            "PersonName" => $address['name_en'],
                            "Title" => "",
                            "CompanyName" => $address['name_en'],
                            "PhoneNumber1" => $address['phone'],
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => "",
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => $address['phone'],
                            "EmailAddress" => $merchentInfo->email ?? 'info@aramex.com',
                            "Type" => "",
                        ]
                    ],
                    "Consignee" => [
                        "Reference1" => "",
                        "Reference2" => "",
                        "AccountNumber" => "",
                        "PartyAddress" => [
                            "Line1" => $shipmentInfo['consignee_address_description'],
                            "Line2" => $address['area'], // $shipment->area->name_en . "-" . $shipment->area->name_ar,
                            "Line3" => $shipmentInfo['consignee_second_phone'],
                            "City" => $shipmentInfo['consignee_city'],
                            "StateOrProvinceCode" => "",
                            "PostCode" => "",
                            "CountryCode" => $merchentInfo->country_code,
                            "Longitude" => 0,
                            "Latitude" => 0,
                            "BuildingNumber" => "",
                            "BuildingName" => "",
                            "Floor" => "",
                            "Apartment" => "",
                            "POBox" => null,
                            "Description" => ""
                        ],

                        "Contact" => [
                            "Department" => "",
                            "PersonName" => $shipmentInfo['consignee_name'],
                            "Title" => "",
                            "CompanyName" => $shipmentInfo['consignee_name'],
                            "PhoneNumber1" => $shipmentInfo['consignee_phone'],
                            "PhoneNumber1Ext" => "",
                            "PhoneNumber2" => $shipmentInfo['consignee_second_phone'],
                            "PhoneNumber2Ext" => "",
                            "FaxNumber" => "",
                            "CellPhone" => $shipmentInfo['consignee_phone'] , // this
                            "EmailAddress" => $shipmentInfo['consignee_email'] ?? "info@aramex.com", // this
                            "Type" => ""
                        ]
                    ],

                    "ShippingDateTime" =>  "/Date(".(Carbon::tomorrow()->getTimestamp()*1000).")/",
                    "DueDate" => "/Date(".(Carbon::tomorrow()->getTimestamp()*1000).")/",
                    "Comments" => $shipmentInfo['notes'] ?? "",
                    "PickupLocation" => "",
                    "OperationsInstructions" => "",
                    "AccountingInstrcutions" => "",
                    "Details" => [
                        "Dimensions" => null,
                        "ActualWeight" => [
                            "Unit" => "KG",
                            "Value" => 1, // this
                        ],
                        "ChargeableWeight" => null,
                        "DescriptionOfGoods" => $shipmentInfo['content'],
                        "GoodsOriginCountry" => "JO",
                        "NumberOfPieces" => $shipmentInfo['pieces'],
                        "ProductGroup" => "DOM", //$shipment->product_group, // DOM, EXP
                        "ProductType" => "DOM", // this
                        "PaymentType" => "P",
                        "PaymentOptions" => "ACCT",
                        "CashOnDeliveryAmount" => [
                            "CurrencyCode" => "JOD",
                            "Value" => 1,
                        ],
                        "CashAdditionalAmount" => [
                            "CurrencyCode" => "JOD",
                            "Value" => 1,
                        ],
                        "CustomsValueAmount" => [
                            "CurrencyCode" => "JOD",
                            "Value" => 1,
                        ],
                        "CashAdditionalAmountDescription" => "",
                        "Services" => 'CODS',
                        "Items" => []
                    ],

                    "Attachments" => [],
                    "ForeignHAWB" => "",
                    "TransportType " => 0,
                    "PickupGUID" => "",
                    "Number" => null,
                    "ScheduledDelivery" => null
                ],
            ],

            "Transaction" =>  [
                "Reference1" => "",
                "Reference2" => "",
                "Reference3" => "",
                "Reference4" => "",
                "Reference5" => ""
            ]
        ];
        */
        $payload = array (
            'ClientInfo' => $this->config,
            'LabelInfo' => 
            array (
              'ReportID' => 9729,
              'ReportType' => 'URL',
            ),
            'Shipments' => 
            array (
              0 => 
              array (
                'Reference1' => 'ShipCash',
                'Reference2' => '',
                'Reference3' => '',
                'Shipper' => 
                array (
                  'Reference1' => $merchentInfo->id,
                  'Reference2' => '',
                  'AccountNumber' => $this->config['AccountNumber'],
                  'PartyAddress' => 
                  array (
                    'Line1' => $address['description'], // 
                    'Line2' => $address['area'],
                    'Line3' => '',
                    'City' => $address['city_code'],
                    'StateOrProvinceCode' => '',
                    'PostCode' => '',
                    'CountryCode' => $merchentInfo->country_code,
                    'Longitude' => 0,
                    'Latitude' => 0,
                    'BuildingNumber' => NULL,
                    'BuildingName' => NULL,
                    'Floor' => NULL,
                    'Apartment' => NULL,
                    'POBox' => NULL,
                    'Description' => '',
                  ),
                  'Contact' => 
                  array (
                    'Department' => '',
                    'PersonName' => $address['name'] ?? 'Tareq Fawakhiri',
                    'Title' => '',
                    'CompanyName' => $address['name'] ?? 'Tareq',
                    'PhoneNumber1' => $address['phone'],
                    'PhoneNumber1Ext' => '',
                    'PhoneNumber2' => '',
                    'PhoneNumber2Ext' => '',
                    'FaxNumber' => '',
                    'CellPhone' => $address['phone'],
                    'EmailAddress' => 'info@aramex.com',
                    'Type' => '',
                  ),
                ),
                'ThirdParty' => 
                array (
                  'Reference1' => '',
                  'Reference2' => '',
                  'AccountNumber' =>  $this->config['AccountNumber'],
                  'PartyAddress' => 
                  array (
                    'Line1' => '',
                    'Line2' => '',
                    'Line3' => '',
                    'City' => '',
                    'StateOrProvinceCode' => '',
                    'PostCode' => '',
                    'CountryCode' => 'JO',
                    'Longitude' => 0,
                    'Latitude' => 0,
                    'BuildingNumber' => NULL,
                    'BuildingName' => NULL,
                    'Floor' => NULL,
                    'Apartment' => NULL,
                    'POBox' => NULL,
                    'Description' => NULL,
                  ),
                  'Contact' => 
                  array (
                    'Department' => '',
                    'PersonName' => '',
                    'Title' => '',
                    'CompanyName' => '',
                    'PhoneNumber1' => '',
                    'PhoneNumber1Ext' => '',
                    'PhoneNumber2' => '',
                    'PhoneNumber2Ext' => '',
                    'FaxNumber' => '',
                    'CellPhone' => '',
                    'EmailAddress' => '',
                    'Type' => '',
                  ),
                ),
                'Consignee' => 
                array (
                  'Reference1' => '',
                  'Reference2' => '',
                  'AccountNumber' => '',
                  'PartyAddress' => 
                  array (
                    'Line1' => $shipmentInfo['consignee_address_description'],
                    'Line2' => $shipmentInfo['consignee_area'],
                    'Line3' => $shipmentInfo['consignee_second_phone'],
                    'City' => $shipmentInfo['consignee_city'],
                    'StateOrProvinceCode' => '',
                    'PostCode' => '', // this
                    'CountryCode' => $merchentInfo->country_code,
                    'Longitude' => 0,
                    'Latitude' => 0,
                    'BuildingNumber' => '',
                    'BuildingName' => '',
                    'Floor' => '',
                    'Apartment' => '',
                    'POBox' => NULL,
                    'Description' => '',
                  ),
                  'Contact' => 
                  array (
                    'Department' => '',
                    'PersonName' => $shipmentInfo['consignee_name'],
                    'Title' => '',
                    'CompanyName' => $shipmentInfo['consignee_name'],
                    'PhoneNumber1' => $shipmentInfo['consignee_phone'],
                    'PhoneNumber1Ext' => '',
                    'PhoneNumber2' => $shipmentInfo['consignee_second_phone'],
                    'PhoneNumber2Ext' => '',
                    'FaxNumber' => '',
                    'CellPhone' => $shipmentInfo['consignee_phone'],
                    'EmailAddress' => 'info@aramex.com',
                    'Type' => '',
                  ),
                ),
                'ShippingDateTime' => '/Date(1635240591000)/',
                'DueDate' => '/Date(1635240591000)/',
                'Comments' => $shipmentInfo['notes'] ?? '',
                'PickupLocation' => '',
                'OperationsInstructions' => '',
                'AccountingInstrcutions' => '',
                'Details' => 
                array (
                  'Dimensions' => NULL,
                  'ActualWeight' => 
                  array (
                    'Unit' => 'KG',
                    'Value' => 0.5,
                  ),
                  'ChargeableWeight' => NULL,
                  'DescriptionOfGoods' => $shipmentInfo['content'],
                  'GoodsOriginCountry' => $merchentInfo->country_code,
                  'NumberOfPieces' => $shipmentInfo['pieces'],
                  'ProductGroup' => 'DOM',
                  'ProductType' => 'COM',
                  'PaymentType' => 'P',
                  'PaymentOptions' => 'ACCT',
                  'CashOnDeliveryAmount' => 
                  array (
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => $shipmentInfo['cod'],
                  ),
                  'CashAdditionalAmount' => 
                  array (
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => 0,
                  ),
                  'CustomsValueAmount' => 
                  array (
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => 0,
                  ),
                  'CashAdditionalAmountDescription' => '',
                  'Services' => ($shipmentInfo['cod'] > 0) ? 'CODS' : '',
                  'Items' => 
                  array (
                  ),
                ),
                'Attachments' => 
                array (
                ),
                'ForeignHAWB' => '',
                'TransportType ' => 0,
                'PickupGUID' => '',
                'Number' => NULL,
                'ScheduledDelivery' => NULL,
              ),
            ),
            'Transaction' => 
            array (
              'Reference1' => '',
              'Reference2' => '',
              'Reference3' => '',
              'Reference4' => '',
              'Reference5' => '',
            ),
        );

        // echo json_encode($payload);die;
        /*
        $payload['Shipments'][0]['Shipper']['Reference1'] = $merchentInfo->id;
        $payload['Shipments'][0]['Shipper']['PartyAddress']['line1'] = $address['area'];
        $payload['Shipments'][0]['Shipper']['PartyAddress']['Line2'] = '';
        $payload['Shipments'][0]['Shipper']['PartyAddress']['Line3'] = '';
        $payload['Shipments'][0]['Shipper']['PartyAddress']['City'] = '';
        $payload['Shipments'][0]['Shipper']['PartyAddress']['CountryCode'] = $merchentInfo->country_code; // add country code to be merchant-> country code

        $payload['Shipments'][0]['Shipper']['Contact']['PersonName'] = $address['name_en'];
        $payload['Shipments'][0]['Shipper']['Contact']['CompanyName'] = $address['name_en'];
        $payload['Shipments'][0]['Shipper']['Contact']['PhoneNumber1'] = $address['phone'];
        $payload['Shipments'][0]['Shipper']['Contact']['CellPhone'] = $address['phone'];
        $payload['Shipments'][0]['Shipper']['Contact']['EmailAddress'] = $merchentInfo->email;

        $payload['Shipments'][0]['Consignee']['PartyAddress']['Line1'] = $shipmentInfo['consignee_address_description']; // consignee description 
        $payload['Shipments'][0]['Consignee']['PartyAddress']['Line2'] = $shipmentInfo['consignee_area']; ; // consginee area 
        $payload['Shipments'][0]['Consignee']['PartyAddress']['Line3'] = $shipmentInfo['consignee_second_phone']; ; // consignee second phone 
        $payload['Shipments'][0]['Consignee']['PartyAddress']['City'] = $shipmentInfo['consignee_city']; ; //  consginee city code  
        $payload['Shipments'][0]['Consignee']['PartyAddress']['CountryCode'] = $merchentInfo->country_code; // merch country code  

        $payload['Shipments'][0]['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name']; // consginee name
        $payload['Shipments'][0]['Consignee']['Contact']['CompanyName'] = $shipmentInfo['consignee_name']; // consignee name 
        $payload['Shipments'][0]['Consignee']['Contact']['PhoneNumber1'] = $shipmentInfo['consignee_phone']; // consignee phone
        $payload['Shipments'][0]['Consignee']['Contact']['PhoneNumber2'] = $shipmentInfo['consignee_second_phone']; // consignee second phone

        $payload['Shipments'][0]['ShippingDateTime'] = Carbon::tomorrow()->getTimestamp(); // timestamp for tomorrow same time of creation
        $payload['Shipments'][0]['DueDate'] = Carbon::tomorrow()->getTimestamp(); //timestamp for tomorrow same time of creation 
        $payload['Shipments'][0]['Comments'] = $shipmentInfo['notes'] ?? ''; // notes 

        $payload['Shipments'][0]['Details']['CashOnDeliveryAmount']['CurrencyCode'] = $merchentInfo->currency_code; // merchant currency 
        $payload['Shipments'][0]['Details']['CashOnDeliveryAmount']['Value'] = 'cod'; // cod 

        $payload['Shipments'][0]['Details']['DescriptionOfGoods'] = $shipmentInfo['content']; // content 
        $payload['Shipments'][0]['Details']['NumberOfPieces'] = $shipmentInfo['pieces']; // picees 
        $payload['Shipments'][0]['Details']['Services'] = ($shipmentInfo['cod'] > 0) ? "CODS" : ""; // if cod > 0 make the value "CODS" else ""
        */
        $response = Http::post(self::$CREATE_SHIPMENTS_URL, $payload);

        
        dd(
            $response->json(),
            $payload,
            // Determine if the status code is >= 200 and < 300...
            $response->successful(),
            // Determine if the response has a 500 level status code...
            $response->serverError()
        );
        dd();
        return $response->json();
    }


}