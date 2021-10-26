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
        if (! $response->successful())
            throw new CarriersException('Aramex Create Shipment – Something Went Wrong');

        if ($response->json()['HasErrors'])
            throw new CarriersException('Aramex Data Provided Not Correct');

        $result = [];
        foreach($response->json()['Shipments'] as $ship){
            $result[] =[
                'id' => $ship['ID'],
                'file' => $ship['ShipmentLabel']['LabelURL']
            ];
        }
        return $result;
    }

    public function shipmentArray($merchentInfo,$address,$shipmentInfo)
    {
        $data = $this->bindJsonFile('shipment.create.json');

        $data['Shipper']['Reference1'] = $merchentInfo->id;
        $data['Shipper']['AccountNumber'] = 
        $data['ThirdParty']['AccountNumber'] = 
            $this->config['AccountNumber'];


        $data['Shipper']['PartyAddress']['CountryCode'] = 
        $data['Consignee']['PartyAddress']['CountryCode'] = 
            $merchentInfo->country_code;

        $data['Details']['CashAdditionalAmount']['CurrencyCode'] =
        $data['Details']['CustomsValueAmount']['CurrencyCode'] =
            $merchentInfo->currency_code;
        
        $data['Shipper']['PartyAddress']['Line1'] = $address['description'];
        $data['Shipper']['PartyAddress']['Line2'] = $address['area'];
        $data['Shipper']['PartyAddress']['City'] = $address['city_code'];

        $data['Shipper']['Contact']['PersonName'] = $address['name'] ?? 'Tareq Fawakhiri';
        $data['Shipper']['Contact']['CompanyName'] = $address['name'] ?? 'Tareq';
        $data['Shipper']['Contact']['PhoneNumber1'] = $address['phone'];
        $data['Shipper']['Contact']['CellPhone'] = $address['phone'];

        $data['Consignee']['PartyAddress']['Line1'] = $shipmentInfo['consignee_address_description'];
        $data['Consignee']['PartyAddress']['Line2'] = $shipmentInfo['consignee_area'];
        $data['Consignee']['PartyAddress']['Line3'] = $shipmentInfo['consignee_second_phone'];
        $data['Consignee']['PartyAddress']['City'] = $shipmentInfo['consignee_city'];

        $data['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name'];
        $data['Consignee']['Contact']['CompanyName'] = $shipmentInfo['consignee_name'];
        $data['Consignee']['Contact']['PhoneNumber1'] = $shipmentInfo['consignee_phone'];
        $data['Consignee']['Contact']['PhoneNumber2'] = $shipmentInfo['consignee_second_phone'];
        $data['Consignee']['Contact']['CellPhone'] = $shipmentInfo['consignee_phone'];

        $data['ShippingDateTime'] = '/Date('.(Carbon::tomorrow()->getTimestamp()*1000).')/';
        $data['DueDate'] = '/Date('.(Carbon::tomorrow()->getTimestamp()*1000).')/';
        $data['Comments'] = $shipmentInfo['notes'] ?? '';
        
        $data['Details']['DescriptionOfGoods'] = $shipmentInfo['content'];
        $data['Details']['GoodsOriginCountry'] = $merchentInfo->country_code;
        $data['Details']['NumberOfPieces'] = $shipmentInfo['pieces'];
        $data['Details']['CashOnDeliveryAmount'] = [
            'CurrencyCode' => $merchentInfo->currency_code,
            'Value' => $shipmentInfo['cod']
        ];

        $data['Details']['Services'] = ($shipmentInfo['cod'] > 0) ? 'CODS' : '';
        return $data;
    }
    
    public function bindJsonFile($file)
    {
        return json_decode(file_get_contents(storage_path().'/../App/Libs/Aramex/'.$file),true);
    }
}