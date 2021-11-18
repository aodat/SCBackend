<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

use XmlParser;

use SimpleXMLElement;

class Fedex
{
    private $account_number, $meter_number, $key, $password;

    private static $stagingUrl = 'https://wsbeta.fedex.com:443/web-services';


    private static $xsd = [
        'CreatePickupRequest' => 'http://fedex.com/ws/pickup/v17',
        'ProcessShipmentRequest' => 'http://fedex.com/ws/ship/v21'
    ];


    private $end_point;
    function __construct()
    {
        $this->account_number = config('carriers.fedex.ACCOUNT_NUMBER');
        $this->meter_number = config('carriers.fedex.METER_NUMBER');
        $this->key = config('carriers.fedex.KEY');
        $this->password = config('carriers.fedex.PASSWORD');
        $this->end_point = self::$stagingUrl;
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

        if (isset($response['HighestSeverity']) && $response['HighestSeverity'])
            throw new CarriersException('FedEx Create Pickup – Something Went Wrong');

        dd($response);
        // if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
        // throw new CarriersException('DHL Create Pickup – Something Went Wrong');
        // return ['id' => $this->config['MessageReference'], 'guid' => $response['ConfirmationNumber']];

    }

    public function cancelPickup($pickupInfo)
    {
        $payload = $this->bindJsonFile('pickup.cancel.json', "CreatePickupRequest");
        $prefix = '##v_id##';
    }

    public function printLabel()
    {
    }

    public function createShipment($merchentInfo, $shipmentInfo)
    {
        $payload = $this->bindJsonFile('shipment.create.json', "ProcessShipmentRequest");

        $payload['ProcessShipmentRequest']['TransactionDetail']['CustomerTransactionId'] = randomNumber(32);
        $payload['ProcessShipmentRequest']['RequestedShipment']['ShipTimestamp'] = Carbon::now()->format(Carbon::ATOM);
        $payload['ProcessShipmentRequest']['RequestedShipment']['Shipper']['Contact'] = [
            'PersonName' => $shipmentInfo['sender_name'],
            'CompanyName' => $shipmentInfo['sender_name'],
            'PhoneNumber' => $shipmentInfo['sender_phone']
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Shipper']['Address'] = [
            'StreetLines' => $shipmentInfo['sender_address_description'],
            'City' => $shipmentInfo['sender_city'],
            'StateOrProvinceCode' => 'GA',
            'PostalCode' => '20000',
            'CountryCode' => $merchentInfo->country_code
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Recipient']['Contact'] = [
            'PersonName' => $shipmentInfo['consignee_name'],
            'CompanyName' => $shipmentInfo['consignee_name'],
            'PhoneNumber' => $shipmentInfo['consignee_phone']
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['Recipient']['Address'] = [
            'StreetLines' => $shipmentInfo['consignee_address_description'],
            'City' => $shipmentInfo['consignee_city'],
            'StateOrProvinceCode' => 'GA',
            'PostalCode' => $shipmentInfo['consignee_zip_code'] ?? '',
            'CountryCode' => $shipmentInfo['consignee_country']
        ];
        $payload['ProcessShipmentRequest']['RequestedShipment']['ShippingChargesPayment']['Payor']['ResponsibleParty']['AccountNumber'] = $this->account_number;
        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['Description'] = $shipmentInfo['notes'] ?? 'No Notes';
        $response = $this->call('ProcessShipmentRequest', $payload);

        if (!isset($response['Notifications']['Severity']))
            throw new CarriersException('FedEx Create Shipment – Something Went Wrong');

        return [
            'id' => $response['CompletedShipmentDetail']['CompletedPackageDetails']['TrackingIds']['TrackingNumber'],
            'file' => uploadFiles('fedex/shipment', base64_decode($response['CompletedShipmentDetail']['CompletedPackageDetails']['Label']['Parts']['Image']), 'pdf', true)
        ];
    }

    public function bindJsonFile($file, $type)
    {
        $payload = json_decode(file_get_contents(storage_path() . '/../App/Libs/Fedex/' . $file), true);
        $payload[$type]['WebAuthenticationDetail']['UserCredential'] = [
            'Key' => $this->key,
            'Password' => $this->password
        ];
        $payload[$type]['ClientDetail'] = [
            'AccountNumber' => $this->account_number,
            'MeterNumber' => $this->meter_number
        ];
        return $payload;
    }

    private function fedexXMLFile($type, $data)
    {
        $xml = new SimpleXMLElement('<SOAP-ENV:Envelope></SOAP-ENV:Envelope>', LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->addAttribute('xmlns', self::$xsd[$type]);

        $body = array_to_xml($data, new SimpleXMLElement('<SOAP-ENV:Body></SOAP-ENV:Body>', LIBXML_NOERROR, false, 'ws', true));


        $main = dom_import_simplexml($xml);
        $content = dom_import_simplexml($body);
        $main->appendChild($main->ownerDocument->importNode($content, true));

        $xml = simplexml_import_dom($main);


        return str_replace('xmlns:xmlns="http://schemas.xmlsoap.org/soap/envelope/"', '', $xml->asXML());
    }

    private function call($type, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fedexXMLFile($type, $data));
        $result = curl_exec($ch);

        curl_error($ch);
        // Parsing SOAP XML File 
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $result);
        $xml = new SimpleXMLElement($response);

        $body = $xml->xpath('//SOAP-ENV:Body')[0];
        return last(json_decode(json_encode((array)$body), true));
    }
}
