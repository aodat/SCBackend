<?php

namespace Libs;

use App\Exceptions\CarriersException;
use App\Http\Controllers\Utilities\AWSServices;
use App\Http\Controllers\Utilities\Shipcash;
use App\Http\Controllers\Utilities\XML;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use SimpleXMLElement;

class DHL
{
    private static $xsd = [
        'BookPURequest' => 'http://www.dhl.com book-pickup-global-req_EA.xsd',
        'CancelPURequest' => 'http://www.dhl.com cancel-pickup-global-req.xsd',
        'ShipmentRequest' => 'http://www.dhl.com ship-val-global-req.xsd',
        'RouteRequest' => 'http://www.dhl.com routing-global-req.xsd',
        'KnownTrackingRequest' => 'http://www.dhl.com TrackingRequestKnown.xsd',
    ];

    private static $schemaVersion = [
        'BookPURequest' => '3.0',
        'CancelPURequest' => '3.0',
        'ShipmentRequest' => '10.0',
        'RouteRequest' => '2.0',
        'KnownTrackingRequest' => '1.0',
    ];

    private static $stagingUrl = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
    private static $productionUrl = 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';

    private $end_point;
    private $account_number;
    private $merchentInfo, $setup;

    public function __construct($settings = null)
    {
        $this->config = [
            'MessageTime' => Carbon::now()->format(Carbon::ATOM),
            'MessageReference' => Shipment::AWBID(32),
            'SiteID' => $settings['dhl_site_id'] ?? config('carriers.dhl.SITE_ID'),
            'Password' => $settings['dhl_password'] ?? config('carriers.dhl.PASSWORD'),
        ];

        $this->end_point = self::$productionUrl;
        if (env('APP_ENV') == 'local') {
            $this->end_point = self::$stagingUrl;
        }

        $this->account_number = $settings['dhl_account_number'] ?? config('carriers.dhl.ACCOUNT_NUMBER');
        $this->merchentInfo = App::make('merchantInfo');

        $this->setup = [
            'PU' => ['status' => 'PROCESSING'],
        ];
    }

    public function __check($countryName, $countryCode, $city, $area = '')
    {
        $payload = $this->bindJsonFile('validate.json');
        $payload['RegionCode'] = 'EU';
        $payload['RequestType'] = 'O';
        $payload['Address1'] = $payload['Address2'] = $payload['Address3'] = $area;
        $payload['PostalCode'] = '';
        $payload['City'] = $city;
        $payload['Division'] = '';
        $payload['CountryCode'] = $countryCode;
        $payload['CountryName'] = $countryName;
        $payload['OriginCountryCode'] = $countryCode;

        $response = $this->call('RouteRequest', $payload);
        if (!empty($response['Response']['Status']['Condition'])) {
            throw new CarriersException('This Country Not Supported IN DHL');
        }

        return true;
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
            "consignee_address_description_1" => "13 DICKENS DR",
            "content" => "Test Content",
            "pieces" => 1,
            "actual_weight" => 1,
            "declared_value" => 1,
            "is_doc" => true,
            "group" => "EXP",
        ];

        return $this->createShipment($merchentInfo, $shipmentInfo, true);
    }

    public function createPickup($email, $info, $address)
    {
        $this->__check($address['country'], $address['country_code'], $address['city'], $address['area']);
        $payload = $this->bindJsonFile('pickup.create.json');
        $payload['Requestor']['AccountNumber'] = $this->account_number;

        $payload['Requestor']['CompanyName'] = $this->merchentInfo->name;
        $payload['Requestor']['Address1'] = $address['name'];
        $payload['Requestor']['City'] = $address['city'];
        $payload['Requestor']['CountryCode'] = $address['country_code'];
        $payload['Requestor']['PostalCode'] = "";
        $payload['Requestor']['RequestorContact']['PersonName'] = $address['name'];
        $payload['Requestor']['RequestorContact']['Phone'] = $address['phone'];

        $payload['Place']['CompanyName'] = $address['name'];
        $payload['Place']['Address1'] = $address['area'];
        $payload['Place']['Address2'] = $address['area'];
        $payload['Place']['PackageLocation'] = substr($address['description'], 0, 30);
        $payload['Place']['City'] = $address['city'];
        $payload['Place']['CountryCode'] = $address['country_code'];
        $payload['Place']['PostalCode'] = "";

        // Pickup
        $payload['Pickup']['PickupDate'] = $info['date'];
        $payload['Pickup']['ReadyByTime'] = $info['ready']->format('H:i');
        $payload['Pickup']['CloseTime'] = $info['close']->format('H:i');

        $payload['PickupContact']['PersonName'] = $address['name'];
        $payload['PickupContact']['Phone'] = $address['phone'];

        $payload['ShipmentDetails']['AccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['BillToAccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['AWBNumber'] = Shipment::AWBID(9);

        $payload['ConsigneeDetails']['CompanyName'] = $this->merchentInfo->name;
        $payload['ConsigneeDetails']['AddressLine'] = $address['area'];
        $payload['ConsigneeDetails']['City'] = $address['city'];
        $payload['ConsigneeDetails']['CountryCode'] = $address['country_code'];
        $payload['ConsigneeDetails']['PostalCode'] = '';
        $payload['ConsigneeDetails']['Contact']['PersonName'] = $address['name'];
        $payload['ConsigneeDetails']['Contact']['Phone'] = $address['phone'];

        $response = $this->call('BookPURequest', $payload);

        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error') {
            throw new CarriersException('DHL Create Pickup – Something Went Wrong', $payload, $response);
        }

        return ['id' => $this->config['MessageReference'], 'guid' => $response['ConfirmationNumber']];
    }

    public function cancelPickup($pickupInfo)
    {
        $payload = $this->bindJsonFile('pickup.cancel.json');
        $payload['RegionCode'] = 'EU';
        $payload['ConfirmationNumber'] = $pickupInfo->hash;
        $payload['RequestorName'] = $pickupInfo->address_info['name'];
        $payload['CountryCode'] = $pickupInfo->address_info['country_code'];
        $payload['OriginSvcArea'] = 'AMM';
        $payload['PickupDate'] = $pickupInfo->pickup_date;
        $payload['CancelTime'] = '10:20';

        $response = $this->call('CancelPURequest', $payload);
        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error') {
            throw new CarriersException('DHL Create Pickup – Something Went Wrong', $payload, $response);
        }

        return true;
    }

    public function createShipment($merchentInfo, $shipmentInfo, $checkAuth = false)
    {
        $payload = $this->bindJsonFile('shipment.create.json');
        $payload['Billing']['ShipperAccountNumber'] = $this->account_number;
        $payload['Billing']['BillingAccountNumber'] = $this->account_number;

        $payload['Consignee']['CompanyName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['AddressLine1'] = substr($shipmentInfo['consignee_address_description_1'], 0, 10);
        $payload['Consignee']['AddressLine2'] = substr($shipmentInfo['consignee_address_description_2'] ?? $shipmentInfo['consignee_address_description_1'], 0, 10);
        $payload['Consignee']['AddressLine3'] = substr($shipmentInfo['consignee_area'], 0, 10);
        $payload['Consignee']['StreetName'] = substr($shipmentInfo['consignee_address_description_1'], 0, 25);
        $payload['Consignee']['BuildingName'] = substr($shipmentInfo['consignee_address_description_1'], 0, 25);
        $payload['Consignee']['StreetNumber'] = substr($shipmentInfo['consignee_address_description_1'], 0, 15);
        $payload['Consignee']['City'] = $shipmentInfo['consignee_city'];
        $payload['Consignee']['PostalCode'] = $shipmentInfo['consignee_zip_code'] ?? '';
        $payload['Consignee']['CountryCode'] = $shipmentInfo['consignee_country'];
        $payload['Consignee']['CountryName'] = $shipmentInfo['consignee_country'];

        $payload['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['Contact']['PhoneNumber'] = $shipmentInfo['consignee_phone'];
        $payload['Consignee']['Contact']['MobilePhoneNumber'] = $shipmentInfo['consignee_second_phone'] ?? $shipmentInfo['consignee_phone'];
        $payload['Consignee']['Contact']['Email'] = $shipmentInfo['consignee_email'];
        $payload['Consignee']['Contact']['PhoneExtension'] = '';

        $payload['ShipmentDetails']['Contents'] = $shipmentInfo['content'] ?? ''; // $shipmentInfo['consignee_notes'] ?? '';
        $payload['ShipmentDetails']['Date'] = Carbon::now()->format('Y-m-d');

        $payload['ShipmentDetails']['Pieces']['Piece']['PieceContents'] = $shipmentInfo['content'] ?? '';
        $payload['ShipmentDetails']['Pieces']['Piece']['Weight'] = $shipmentInfo['actual_weight'] ?? '';

        $docCode = ($shipmentInfo['is_doc']) ? 'D' : 'P';
        $payload['ShipmentDetails']['GlobalProductCode'] =
            $payload['ShipmentDetails']['LocalProductCode'] = $docCode;

        $payload['Shipper']['ShipperID'] = $merchentInfo->id;
        $payload['Shipper']['CompanyName'] = substr($shipmentInfo['sender_name'], 0, 25);
        $payload['Shipper']['AddressLine1'] = substr($shipmentInfo['sender_address_description'], 0, 25);
        $payload['Shipper']['AddressLine2'] = substr($shipmentInfo['sender_area'], 0, 25);
        $payload['Shipper']['AddressLine3'] = substr($shipmentInfo['sender_area'], 0, 25);
        $payload['Shipper']['City'] = $shipmentInfo['sender_city'];
        $payload['Shipper']['CountryCode'] = $merchentInfo->country_code;
        $payload['Shipper']['CountryName'] = $merchentInfo->country_code;
        $payload['Shipper']['StreetName'] = $shipmentInfo['sender_area'];
        $payload['Shipper']['BuildingName'] = substr($shipmentInfo['sender_area'], 0, 30);
        $payload['Shipper']['StreetNumber'] = substr($shipmentInfo['sender_address_description'], 0, 15);

        $payload['Shipper']['Contact']['PersonName'] = substr($shipmentInfo['sender_name'], 0, 25);
        $payload['Shipper']['Contact']['PhoneNumber'] = $shipmentInfo['sender_phone'];
        $payload['Shipper']['Contact']['MobilePhoneNumber'] = $shipmentInfo['sender_phone'];
        $payload['Shipper']['Contact']['Email'] = $merchentInfo->email;

        $payload['Dutiable']['DeclaredValue'] = Shipcash::exchange($shipmentInfo['declared_value'], $merchentInfo->currency_code, 'USD');
        $payload['Dutiable']['DeclaredCurrency'] = 'USD';

        $response = $this->call('ShipmentRequest', $payload);

        if ($checkAuth) {
            if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error') {
                return false;
            } else {
                return true;
            }
        }

        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error') {
            throw new CarriersException('DHL Create Shipment – Something Went Wrong', $payload, $response);
        }

        return [
            'id' => $response['AirwayBillNumber'],
            'file' => AWSServices::uploadToS3('dhl/shipment', base64_decode($response['LabelImage']['OutputImage']), 'pdf', true),
        ];
    }

    public function trackShipment($shipment_waybills)
    {
        $payload = $this->bindJsonFile('track.json');
        $payload['AWBNumber'] = $shipment_waybills;

        $response = $this->call('KnownTrackingRequest', $payload);

        if (isset($response['Response']['Status']) && ($response['Response']['Status']['ActionStatus'] == 'Error' || $response['Response']['Status']['ActionStatus'] == 'Failure')) {
            return [];
        }

        return $response['AWBInfo']['ShipmentInfo'] ?? [];
    }

    public function bindJsonFile($file)
    {
        $payload = json_decode(file_get_contents(app_path() . '/Libs/DHL/' . $file), true);
        $payload['Request']['ServiceHeader'] = $this->config;

        return $payload;
    }

    private function dhlXMLFile($type, $data, $prefix)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>' . "<$prefix:$type></$prefix:$type>", LIBXML_NOERROR, false, 'ws', true);
        if ($prefix == 'req') {
            $xml->addAttribute('req:xmlns:req', 'http://www.dhl.com');
            $xml->addAttribute('req:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $xml->addAttribute('req:xsi:schemaLocation', self::$xsd[$type]);
            $xml->addAttribute('req:schemaVersion', self::$schemaVersion[$type]);
        } else {
            $xml->addAttribute('xmlns:ns1', 'http://www.dhl.com');
            $xml->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $xml->addAttribute('xsi:schemaLocation', self::$xsd[$type]);
            $xml->addAttribute('schemaVersion', self::$schemaVersion[$type]);
        }

        return XML::toXML($data, $xml);
    }

    private function call($type, $data, $prefix = 'req')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->dhlXMLFile($type, $data, $prefix)->asXML());
        $result = curl_exec($ch);
        curl_error($ch);
        return XML::toArray($result);
    }
}
