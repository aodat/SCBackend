<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Aramex
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
        $payload = $this->bindJsonFile('pickup.create.json');
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


        if (! $response->successful())
            throw new CarriersException('Aramex Create Pickup – Something Went Wrong');
        if ($response->json()['HasErrors'])
            throw new CarriersException('Aramex Data Provided Not Correct');
        
        $final = $response->json();
        return ['id' => $final['ProcessedPickup']['ID'] , 'guid' => $final['ProcessedPickup']['GUID']];
    }

    public function cancelPickup($pickup_guid)
    {
        $payload = ["ClientInfo" => $this->config,"PickupGUID" => $pickup_guid,];
        $response = Http::post(self::$CANCEL_PICKUP_URL, $payload);
        if (! $response->successful())
            throw new CarriersException('Aramex Cancel Pickup – Something Went Wrong');
        if ($response->json()['HasErrors'])
            throw new CarriersException('Aramex Data Provided Not Correct');

        return true;
    }

    public function printLabel($shipments, $ReportID = 9729)
    {
        $payload =  [
            "ClientInfo" => $this->config,
            "LabelInfo" => ["ReportID" => $ReportID,"ReportType" => "URL"]
        ];
        $files = [];
        foreach($shipments as $shipment){
            $payload['ShipmentNumber'] = $shipment;
    
            $response = Http::post(self::$PRINT_LABEL_URL, $payload);
            if (! $response->successful())
                throw new CarriersException('Aramex Print Label – Something Went Wrong');
            if ($response->json()['HasErrors'])
                throw new CarriersException('Aramex Data Provided Not Correct');
            
            $files[] = $response->json()['ShipmentLabel']['LabelURL'];
        }
        return $files;
    }

    public function createShipment($shipmentArray)
    {
        $payload = [
            'ClientInfo' => $this->config,
            'LabelInfo' => ['ReportID' => 9729,'ReportType' => 'URL'],
            'Shipments' => $shipmentArray,
            'Transaction' => [
                'Reference1' => '',
                'Reference2' => '',
                'Reference3' => '',
                'Reference4' => '',
                'Reference5' => '',
            ]
        ];
        $response = Http::post(self::$CREATE_SHIPMENTS_URL, $payload);
        return $response->json();
    }

    public function shipmentArray($merchentInfo,$address,$shipmentInfo){
        $ship = [
            'Reference1' => 'ShipCash',
            'Reference2' => '',
            'Reference3' => '',
            'Shipper' => 
            [
                'Reference1' => $merchentInfo->id,
                'Reference2' => '',
                'AccountNumber' => $this->config['AccountNumber'],
                'PartyAddress' => [
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
                ],
                'Contact' => [
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
                ],
            ],
            'ThirdParty' => [
                'Reference1' => '',
                'Reference2' => '',
                'AccountNumber' =>  $this->config['AccountNumber'],
                    'PartyAddress' => [
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
                ],
                'Contact' => [
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
                ],
            ],
            'Consignee' => [
                'Reference1' => '',
                'Reference2' => '',
                'AccountNumber' => '',
                'PartyAddress' => [
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
                ],
                'Contact' => [
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
                ],
            ],
            'ShippingDateTime' => '/Date('.(Carbon::tomorrow()->getTimestamp()*1000).')/',
            'DueDate' => '/Date('.(Carbon::tomorrow()->getTimestamp()*1000).')/',
            'Comments' => $shipmentInfo['notes'] ?? '',
            'PickupLocation' => '',
            'OperationsInstructions' => '',
            'AccountingInstrcutions' => '',
            'Details' => [
                'Dimensions' => NULL,
                'ActualWeight' => ['Unit' => 'KG','Value' => 0.5,],
                'ChargeableWeight' => NULL,
                'DescriptionOfGoods' => $shipmentInfo['content'],
                'GoodsOriginCountry' => $merchentInfo->country_code,
                'NumberOfPieces' => $shipmentInfo['pieces'],
                'ProductGroup' => 'DOM',
                'ProductType' => 'COM',
                'PaymentType' => 'P',
                'PaymentOptions' => 'ACCT',
                'CashOnDeliveryAmount' => [
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => $shipmentInfo['cod'],
                ],
                'CashAdditionalAmount' => [
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => 0,
                ],
                'CustomsValueAmount' => [
                    'CurrencyCode' => $merchentInfo->currency_code,
                    'Value' => 0,
                ],
                'CashAdditionalAmountDescription' => '',
                'Services' => ($shipmentInfo['cod'] > 0) ? 'CODS' : '',
                'Items' => [],
            ],
            'Attachments' => [],
            'ForeignHAWB' => '',
            'TransportType ' => 0,
            'PickupGUID' => '',
            'Number' => NULL,
            'ScheduledDelivery' => NULL,
        ];

        return $ship;
    }
    
    public function bindJsonFile($file)
    {
        return json_decode(file_get_contents(storage_path().'/../App/Libs/Aramex/'.$file),true);
    }
}