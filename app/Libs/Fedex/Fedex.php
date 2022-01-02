<?php

namespace Libs;

use App\Exceptions\CarriersException;
use App\Models\City;
use App\Models\Merchant;
use Carbon\Carbon;

use SimpleXMLElement;

class Fedex
{
    private $account_number, $meter_number, $key, $password;

    private static $stagingUrl = 'https://wsbeta.fedex.com:443/web-services';


    private static $xsd = [
        'CreatePickupRequest' => 'http://fedex.com/ws/pickup/v17',
        'ProcessShipmentRequest' => 'http://fedex.com/ws/ship/v21',
        'CancelPickupRequest' => 'http://fedex.com/ws/pickup/v22'
    ];


    private $end_point;
    private $prefix = '';
    private $xmlPrefix = '';
    function __construct()
    {
        $this->account_number = config('carriers.fedex.ACCOUNT_NUMBER');
        $this->meter_number = config('carriers.fedex.METER_NUMBER');
        $this->key = config('carriers.fedex.KEY');
        $this->password = config('carriers.fedex.PASSWORD');
        $this->end_point = self::$stagingUrl;
    }


    public function __check($countryName, $countryCode, $city, $area = '')
    {
        $xml = simplexml_load_file(app_path() . '/Libs/Fedex/validate.xml');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->end_point,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml->asXML(),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/xml'
            ),
        ));
        $response = curl_exec($curl);

        curl_close($curl);

        $xml = new SimpleXMLElement($response);
        $body = $xml->xpath('//SOAP-ENV:Body')[0];
        $response = (last(json_decode(json_encode((array)$body), true)));
        if (isset($response['HighestSeverity']) && $response['HighestSeverity'] == 'FAILURE')
            throw new CarriersException('FedEx This Country Not Supported');
        return true;
    }
    public function createPickup($email, $date, $address)
    {
        $this->__check($address['country'], $address['country_code'], $address['city'], $address['area']);

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
            throw new CarriersException('FedEx Create pickup – Something Went Wrong', $payload, $response);

        dd($response);
        // return ['id' => $this->config['MessageReference'], 'guid' => $response['ConfirmationNumber']];
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
            "vid-EMailAddress" => ""
        ];
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-ResponsibleParty']['vid-Address'] = [
            "vid-StreetLines" => "",
            "vid-City" => $address->city_code,
            "vid-StateOrProvinceCode" => "TN",
            "vid-PostalCode" => "",
            "vid-CountryCode" => $address->country_code,
            "vid-GeographicCoordinates" => ""
        ];
        $payload['vid-CancelPickupRequest']['vid-Payor']['vid-AssociatedAccounts']['vid-AccountNumber'] = $this->account_number;
        $payload['vid-CancelPickupRequest']['vid-ContactName'] = $address->name;

        $this->call('CancelPickupRequest', $payload, true);

        return true;
    }

    public function createShipment($merchentInfo, $shipmentInfo)
    {
        $payload = $this->bindJsonFile('shipment.create.json', "ProcessShipmentRequest");

        $payload['ProcessShipmentRequest']['TransactionDetail']['CustomerTransactionId'] =
            $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['CustomerReferences']['Value'] =
            randomNumber(32);

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
            'City' => $shipmentInfo['consignee_area'],
            'StateOrProvinceCode' => City::where('name_en', $shipmentInfo['consignee_city'])->first() ? City::where('name_en', $shipmentInfo['consignee_city'])->first()->code : '',
            'PostalCode' => $shipmentInfo['consignee_zip_code'] ?? '',
            'CountryCode' => $shipmentInfo['consignee_country']
        ];

        $payload['ProcessShipmentRequest']['RequestedShipment']['ShippingChargesPayment']['Payor']['ResponsibleParty']['AccountNumber'] = $this->account_number;
        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['Description'] = $shipmentInfo['notes'] ?? 'No Notes';


        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['Weight']['Value'] =
            $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['Weight']['Value'] =
            $payload['ProcessShipmentRequest']['RequestedShipment']['RequestedPackageLineItems']['CustomerReferences']['Value'] =
            $payload['ProcessShipmentRequest']['RequestedShipment']['TotalWeight']['Value'] =
            number_format($shipmentInfo['actual_weight'], 2, '.', '');

        $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['CustomsValue']['Amount'] =
            $payload['ProcessShipmentRequest']['RequestedShipment']['CustomsClearanceDetail']['Commodities']['UnitPrice']['Amount'] =
            currency_exchange($shipmentInfo['fees'], $merchentInfo->currency_code);

        
        $response = $this->call('ProcessShipmentRequest', $payload);
        // dd($response);
        if (!isset($response['Notifications']['Severity']) || (isset($response['Notifications']['Severity']) && $response['Notifications']['Severity'] == 'ERROR'))
            throw new CarriersException('FedEx Create Shipment – Something Went Wrong', $payload, $response);

        return [
            'id' => $response['CompletedShipmentDetail']['CompletedPackageDetails']['TrackingIds']['TrackingNumber'],
            'file' => uploadFiles('fedex/shipment', base64_decode($response['CompletedShipmentDetail']['CompletedPackageDetails']['Label']['Parts']['Image']), 'pdf', true)
        ];
    }

    public function bindJsonFile($file, $type)
    {
        $payload = json_decode(file_get_contents(app_path() . '/Libs/Fedex/' . $file), true);
        $payload[$this->prefix . '' . $type][$this->prefix . '' . 'WebAuthenticationDetail'][$this->prefix . '' . 'UserCredential'] = [
            'Key' => $this->key,
            'Password' => $this->password
        ];
        $payload[$this->prefix . '' . $type][$this->prefix . '' . 'ClientDetail'] = [
            'AccountNumber' => $this->account_number,
            'MeterNumber' => $this->meter_number
        ];
        return $payload;
    }

    private function fedexXMLFile($type, $data, $withPrefix)
    {
        $xml = new SimpleXMLElement('<SOAP-ENV:Envelope></SOAP-ENV:Envelope>', LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->addAttribute('xmlns', self::$xsd[$type]);

        $body = array_to_xml($data, new SimpleXMLElement('<SOAP-ENV:Body></SOAP-ENV:Body>', LIBXML_NOERROR, false, 'ws', true));


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
        if ($result == '')
            throw new CarriersException('FedEx - Something Went Wrong', $request, $result);

        // Parsing SOAP XML File 
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $result);
        $xml = new SimpleXMLElement($response);

        $body = $xml->xpath('//SOAP-ENV:Body')[0];
        return last(json_decode(json_encode((array)$body), true));
    }
}
