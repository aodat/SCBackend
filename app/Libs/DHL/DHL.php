<?php

namespace Libs;

use Carbon\Carbon;

use App\Exceptions\CarriersException;

use App\Models\Merchant;
use App\Models\Pickup;
use Illuminate\Support\Facades\App;
use SimpleXMLElement;

class DHL
{
    private static $xsd = [
        'BookPURequest' => 'http://www.dhl.com book-pickup-global-req_EA.xsd',
        'CancelPURequest' => 'http://www.dhl.com cancel-pickup-global-req.xsd',
        'ShipmentRequest' => 'http://www.dhl.com ship-val-global-req.xsd',
        'RouteRequest' => 'http://www.dhl.com routing-global-req.xsd'
    ];

    private static $schemaVersion = [
        'BookPURequest' => '3.0',
        'CancelPURequest' => '3.0',
        'ShipmentRequest' => '10.0',
        'RouteRequest' => '2.0'
    ];

    private static $stagingUrl = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
    private static $productionUrl = 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';

    private $end_point;
    private $account_number;
    private $merchentInfo;
    function __construct()
    {
        $this->config = [
            'MessageTime' => Carbon::now()->format(Carbon::ATOM),
            'MessageReference' => randomNumber(32),
            'SiteID' => config('carriers.dhl.SITE_ID'),
            'Password' =>  config('carriers.dhl.PASSWORD')
        ];

        $this->end_point = self::$stagingUrl;
        $this->account_number = config('carriers.dhl.ACCOUNT_NUMBER');
        $this->merchentInfo = App::make('merchantInfo');
    }

    public function __check($address)
    {
        $payload = $this->bindJsonFile('validate.json');
        $payload['RegionCode'] = 'EU';
        $payload['RequestType'] = 'O';
        $payload['Address1'] = $payload['Address2'] = $payload['Address3'] = $address['area'];
        $payload['PostalCode'] = '';
        $payload['City'] =  $address['city'];
        $payload['Division'] = '';
        $payload['CountryCode'] = $address['country_code'];
        $payload['CountryName'] = $address['country'];
        $payload['OriginCountryCode'] = $address['country_code'];

        $response = $this->call('RouteRequest', $payload);

        if (!empty($response['Response']['Status']['Condition']))
            throw new CarriersException('DHL This Country Not Supported');

        return true;
    }

    public function createPickup($email, $date, $address)
    {
        $this->__check($address);
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
        $payload['Place']['PackageLocation'] = $address['description'];
        $payload['Place']['City'] = $address['city'];
        $payload['Place']['CountryCode'] = $address['country_code'];
        $payload['Place']['PostalCode'] = "";

        // Pickup
        $payload['Pickup']['PickupDate'] = $date;
        $payload['Pickup']['ReadyByTime'] = '15:00';
        $payload['Pickup']['CloseTime'] = '16:00';

        $payload['PickupContact']['PersonName'] = $address['name'];
        $payload['PickupContact']['Phone'] = $address['phone'];

        $payload['ShipmentDetails']['AccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['BillToAccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['AWBNumber'] = randomNumber(9);

        $payload['ConsigneeDetails']['CompanyName'] = $this->merchentInfo->name;
        $payload['ConsigneeDetails']['AddressLine'] = $address['area'];
        $payload['ConsigneeDetails']['City'] = $address['city'];
        $payload['ConsigneeDetails']['CountryCode'] =  $address['country_code'];
        $payload['ConsigneeDetails']['PostalCode'] = '';
        $payload['ConsigneeDetails']['Contact']['PersonName'] = $address['name'];
        $payload['ConsigneeDetails']['Contact']['Phone'] = $address['phone'];

        $response = $this->call('BookPURequest', $payload);
        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong', $payload, $response);

        return ['id' => $this->config['MessageReference'], 'guid' => $response['ConfirmationNumber']];
    }

    public function cancelPickup($pickupInfo)
    {
        $address = Merchant::getAdressInfoByID($pickupInfo->address_id);
        $payload = $this->bindJsonFile('pickup.cancel.json');

        $payload['RegionCode'] = 'EU';
        $payload['ConfirmationNumber'] = $pickupInfo->hash;
        $payload['RequestorName'] = $address->name;
        $payload['CountryCode'] = $address->country_code;
        $payload['PickupDate'] = $pickupInfo->pickup_date;
        $payload['CancelTime'] = '10:20';

        $response = $this->call('CancelPURequest', $payload);
        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong', $payload, $response);

        return true;
    }

    public function printLabel()
    {
    }

    public function createShipment($merchentInfo, $shipmentInfo)
    {
        $payload = $this->bindJsonFile('shipment.create.json');

        $payload['Billing']['ShipperAccountNumber'] = $this->account_number;
        $payload['Billing']['BillingAccountNumber'] = $this->account_number;

        $payload['Consignee']['CompanyName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['AddressLine1'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['AddressLine2'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['AddressLine3'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['StreetName'] =  $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['BuildingName'] =  $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['StreetNumber'] = '';

        $payload['Consignee']['City'] = $shipmentInfo['consignee_city'];
        $payload['Consignee']['PostalCode'] = $shipmentInfo['consignee_zip_code'] ?? '';
        $payload['Consignee']['CountryCode'] = $shipmentInfo['consignee_country'];
        $payload['Consignee']['CountryName'] = $shipmentInfo['consignee_country'];

        $payload['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['Contact']['PhoneNumber'] = $shipmentInfo['consignee_phone'];
        $payload['Consignee']['Contact']['MobilePhoneNumber'] = $shipmentInfo['consignee_second_phone'];
        $payload['Consignee']['Contact']['Email'] = $shipmentInfo['consignee_email'];
        $payload['Consignee']['Contact']['PhoneExtension'] = '';

        $payload['ShipmentDetails']['Contents'] = $shipmentInfo['content'];
        $payload['ShipmentDetails']['Date'] = Carbon::now()->format('Y-m-d');

        $payload['Shipper']['ShipperID'] = $merchentInfo->id;
        $payload['Shipper']['CompanyName'] = $shipmentInfo['sender_name'];
        $payload['Shipper']['AddressLine1'] = $shipmentInfo['sender_address_description'];
        $payload['Shipper']['AddressLine2'] = $shipmentInfo['sender_area'];
        $payload['Shipper']['AddressLine3'] = $shipmentInfo['sender_area'];
        $payload['Shipper']['City'] = $shipmentInfo['sender_city'];
        $payload['Shipper']['CountryCode'] =  $merchentInfo->country_code;
        $payload['Shipper']['CountryName'] = $merchentInfo->country_code;
        $payload['Shipper']['StreetName'] =  $shipmentInfo['sender_area'];
        $payload['Shipper']['BuildingName'] =  $shipmentInfo['sender_area'];
        $payload['Shipper']['StreetNumber'] = '';

        $payload['Shipper']['Contact']['PersonName'] = $shipmentInfo['sender_name'];
        $payload['Shipper']['Contact']['PhoneNumber'] = $shipmentInfo['sender_phone'];
        $payload['Shipper']['Contact']['MobilePhoneNumber'] = $shipmentInfo['sender_phone'];
        $payload['Shipper']['Contact']['Email'] = $merchentInfo->email;
        $response = $this->call('ShipmentRequest', $payload);
        if (isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Shipment – Something Went Wrong', $payload, $response);

        return [
            'id' => $response['DHLRoutingCode'],
            'file' => uploadFiles('dhl/shipment', base64_decode($response['LabelImage']['OutputImage']), 'pdf', true)
        ];
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
        return array_to_xml($data, $xml);
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

        return XMLToArray($result);
    }
}
