<?php

namespace Libs;

use App\Exceptions\CarriersException;
use App\Http\Controllers\Utilities\AWSServices;
use App\Http\Controllers\Utilities\Shipcash;
use App\Http\Controllers\Utilities\XML;
use App\Models\City;
use App\Models\Merchant;
use App\Models\Shipment;
use Carbon\Carbon;
use SimpleXMLElement;

class Fedex
{
    private $account_number, $meter_number, $key, $password;

    private static $stagingUrl = 'https://wsbeta.fedex.com:443/web-services';
    private static $productionUrl = 'https://ws.fedex.com:443/web-services';

    private static $xsd = [
        'CreatePickupRequest' => 'http://fedex.com/ws/pickup/v17',
        'ProcessShipmentRequest' => 'http://fedex.com/ws/ship/v21',
        'CancelPickupRequest' => 'http://fedex.com/ws/pickup/v22',
        'TrackRequest' => 'http://fedex.com/ws/track/v20',
    ];

    private $end_point;
    private $prefix = '';
    private $xmlPrefix = '';

    public function __construct($settings = null)
    {

        $this->account_number = $settings['fedex_account_number'] ?? config('carriers.fedex.ACCOUNT_NUMBER');
        $this->meter_number = $settings['fedex_meter_number'] ?? config('carriers.fedex.METER_NUMBER');
        $this->key = $settings['fedex_key'] ?? config('carriers.fedex.KEY');
        $this->password = $settings['fedex_password'] ?? config('carriers.fedex.PASSWORD');

        $this->end_point = self::$stagingUrl;
        if (config('app.env') == 'production') {
            $this->end_point = self::$productionUrl;
        }

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

    public function createPickup($email, $date, $address)
    {
        $payload = $this->bindJsonFile('pickup.create.json', "CreatePickupRequest");

        $payload['CreatePickupRequest']['AssociatedAccountNumber']['AccountNumber'] = $this->account_number;
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Contact']['CompanyName'] = $address['name'];
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Contact']['PersonName'] = $address['name'];
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Contact']['PhoneNumber'] = $address['phone'];
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Contact']['EMailAddress'] = $email;

        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Address']['StreetLines'] = $address['description'];
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Address']['City'] = $address['city'];
        $payload['CreatePickupRequest']['OriginDetail']['PickupLocation']['Address']['CountryCode'] = $address['country_code'];

        $payload['CreatePickupRequest']['OriginDetail']['BuildingPartDescription'] = $address['area'];
        $payload['CreatePickupRequest']['OriginDetail']['ReadyTimestamp'] = date('c', strtotime($date . ' 03:00 PM'));
        $response = $this->call('CreatePickupRequest', $payload);

        if (!isset($response['Notifications']['Severity']) || (isset($response['Notifications']['Severity']) && $response['Notifications']['Severity'] == 'ERROR')) {
            throw new CarriersException('FedEx Create pickup – Something Went Wrong', $payload, $response);
        }

        return ['id' => Shipment::AWBID(32), 'guid' => $response['PickupConfirmationNumber']];
    }

    public function cancelPickup($pickupInfo)
    {
        $this->prefix = 'vid-';
        $this->xmlPrefix = 'v22:';

        $address = Merchant::getAdressInfoByID($pickupInfo->address_id);
        $payload = $this->bindJsonFile('pickup.cancel.json', "CancelPickupRequest");

        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-ResponsibleParty']['vid-AccountNumber'] = $this->account_number;
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-ResponsibleParty']['vid-Tins']['vid-Number'] = $this->account_number;
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-ResponsibleParty']['vid-Contact'] = [
            "vid-ContactId" => "KR1059",
            "vid-PersonName" => $address->name,
            "vid-Title" => "Mr.",
            "vid-CompanyName" => $address->name,
            "vid-PhoneNumber" => $address->phone,
            "vid-PhoneExtension" => "",
            "vid-PagerNumber" => "",
            "vid-FaxNumber" => "",
            "vid-EMailAddress" => "",
        ];
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-ResponsibleParty']['vid-Address'] = [
            "vid-StreetLines" => "",
            "vid-City" => $address->city_code,
            // "vid-StateOrProvinceCode" => "",
            "vid-PostalCode" => "",
            "vid-CountryCode" => $address->country_code,
            "vid-GeographicCoordinates" => "",
        ];
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-AssociatedAccounts']['vid-AccountNumber'] = $this->account_number;
        $payload['vid-CancelPickupRequest']['vid-ContactName'] = $address->name;

        $this->call('CancelPickupRequest', $payload, true);

        return true;
    }

    public function createShipment($merchentInfo, $shipmentInfo, $checkAuth = false)
    {
        $payload = $this->bindJsonFile('shipment.create.json', "ProcessShipmentRequest");

        $payload['ProcessShipmentRequest']['TransactionDetail']['CustomerTransactionId'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['CustomerReferences']['Value'] =
        Shipment::AWBID(32);

        $payload['ProcessShipmentRequest']['RequestedShipment']['ShipTimestamp'] = Carbon::now()->format(Carbon::ATOM);
        $payload['ProcessShipmentRequest']['RequestedShipment']['Shipper']['Contact'] = [
            'PersonName' => $shipmentInfo['sender_name'],
            'CompanyName' => $shipmentInfo['sender_name'],
            'PhoneNumber' => $shipmentInfo['sender_phone'],
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Shipper']['Address'] = [
            'StreetLines' => $shipmentInfo['sender_address_description'],
            'City' => $shipmentInfo['sender_city'],
            // 'StateOrProvinceCode' => 'GA',
            'PostalCode' => '20000',
            'CountryCode' => $merchentInfo->country_code,
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Recipient']['Contact'] = [
            'PersonName' => $shipmentInfo['consignee_name'],
            'CompanyName' => $shipmentInfo['consignee_name'],
            'PhoneNumber' => $shipmentInfo['consignee_phone'],
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Recipient']['Address'] = [
            'StreetLines' => $shipmentInfo['consignee_address_description_1'],
            'City' => $shipmentInfo['consignee_area'],
            'StateOrProvinceCode' => City::where('name_en', $shipmentInfo['consignee_city'])->first() ? City::where('name_en', $shipmentInfo['consignee_city'])->first()->code : '',
            'PostalCode' => $shipmentInfo['consignee_zip_code'] ?? '',
            'CountryCode' => $shipmentInfo['consignee_country'],
        ];

        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['DutiesPayment']['Payor']['ResponsibleParty']['AccountNumber'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['ShippingChargesPayment']['Payor']['ResponsibleParty']['AccountNumber'] = $this->account_number;
        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['Description'] = $shipmentInfo['notes'] ?? 'No Notes';

        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['Weight']['Value'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['Weight']['Value'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['CustomerReferences']['Value'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['TotalWeight']['Value'] =
            number_format($shipmentInfo['actual_weight'], 2, '.', '');

        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] =
        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['UnitPrice']['Amount'] =
        Shipcash::exchange($shipmentInfo['declared_value'], $merchentInfo->currency_code);

        $response = $this->call('ProcessShipmentRequest', $payload);

        if ($checkAuth) {
            if (
                (!isset($response['Notifications']['Severity'])) ||
                (isset($response['Notifications']['Severity']) && $response['Notifications']['Severity'] == 'ERROR') ||
                (!isset($response['CompletedShipmentDetail']))
            ) {
                return false;
            } else {
                return true;
            }
        }

        if (
            (!isset($response['Notifications']['Severity'])) ||
            (isset($response['Notifications']['Severity']) && $response['Notifications']['Severity'] == 'ERROR') ||
            (!isset($response['CompletedShipmentDetail']))
        ) {
            throw new CarriersException('FedEx Create Shipment – Something Went Wrong', $payload, $response);
        }

        return [
            'id' => $response['CompletedShipmentDetail']['CompletedPackageDetails']['TrackingIds']['TrackingNumber'],
            'file' => AWSServices::uploadToS3('fedex/shipment', base64_decode($response['CompletedShipmentDetail']['CompletedPackageDetails']['Label']['Parts']['Image']), 'pdf', true),
        ];
    }

    public function trackShipment($shipment_waybills)
    {
        $payload = $this->bindJsonFile('track.json', "TrackRequest");
        $payload['TrackRequest']['SelectionDetails']['PackageIdentifier']['Value'] = $shipment_waybills;

        $response = $this->call('TrackRequest', $payload);

        if (
            (!isset($response['v20Notifications']['Severity'])) ||
            (isset($response['v20Notifications']['Severity']) && $response['v20Notifications']['Severity'] == 'ERROR')
        ) {
            throw new CarriersException('Cannot track Fedex shipment');
        }

        return ($response);
    }

    public function bindJsonFile($file, $type)
    {
        $payload = json_decode(file_get_contents(app_path() . '/Libs/Fedex/' . $file), true);
        $payload[$this->prefix . '' . $type][$this->prefix . '' . 'WebAuthenticationDetail'][$this->prefix . '' . 'UserCredential'] = [
            'Key' => $this->key,
            'Password' => $this->password,
        ];
        $payload[$this->prefix . '' . $type][$this->prefix . '' . 'ClientDetail'] = [
            'AccountNumber' => $this->account_number,
            'MeterNumber' => $this->meter_number,
        ];
        return $payload;
    }

    private function fedexXMLFile($type, $data, $withPrefix)
    {
        $xml = new SimpleXMLElement('<SOAP-ENV:Envelope></SOAP-ENV:Envelope>', LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->addAttribute('xmlns', self::$xsd[$type]);

        $body = XML::toXML($data, new SimpleXMLElement('<SOAP-ENV:Body></SOAP-ENV:Body>', LIBXML_NOERROR, false, 'ws', true));

        $main = dom_import_simplexml($xml);
        $content = dom_import_simplexml($body);
        $main->appendChild($main->ownerDocument->importNode($content, true));

        $xml = simplexml_import_dom($main);
        $xml = str_replace('xmlns:xmlns="http://schemas.xmlsoap.org/soap/envelope/"', '', $xml->asXML());
        if ($withPrefix) {
            $xml = str_replace($this->prefix, $this->xmlPrefix, $xml);
            $xml = str_replace('xmlns="', 'v22:xmlns="', $xml);
        }
        return $xml;
    }

    private function call($type, $data, $withPrefix = false)
    {
        $request = $this->fedexXMLFile($type, $data, $withPrefix);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        $result = curl_exec($ch);

        curl_error($ch);
        if ($result == '') {
            throw new CarriersException('FedEx - Something Went Wrong', $request, $result);
        }

        // Parsing SOAP XML File
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $result);
        $xml = new SimpleXMLElement($response);

        $body = $xml->xpath('//SOAP-ENV:Body')[0];
        return last(json_decode(json_encode((array) $body), true));
    }
}
