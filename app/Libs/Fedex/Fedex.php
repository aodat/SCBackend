<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

use SimpleXMLElement;

class Fedex
{
    private $account_number, $meter_number, $key, $password;

    private static $stagingUrl = 'https://wsbeta.fedex.com:443/web-services';

    private $base_url;
    function __construct()
    {
        $this->account_number = config('carriers.fedex.ACCOUNT_NUMBER');
        $this->meter_number = config('carriers.fedex.METER_NUMBER');
        $this->key = config('carriers.fedex.KEY');
        $this->password = config('carriers.fedex.PASSWORD');
        $this->base_url = self::$stagingUrl;
    }

    public function createPickup($email, $date, $address)
    {
        $payload = $this->bindJsonFile('pickup.create.json', "CreatePickupRequest");

        echo ($this->fedexXMLFile($payload)->asXML());

        die;
    }

    public function cancelPickup($pickupInfo)
    {
    }

    public function printLabel()
    {
    }

    public function createShipment($merchentInfo, $shipmentInfo)
    {
    }

    public function bindJsonFile($file, $type)
    {
        $payload = json_decode(file_get_contents(storage_path() . '/../App/Libs/Fedex/' . $file), true);
        $payload[$type]['WebAuthenticationDetail']['UserCredential'] = [
            'key' => $this->key,
            'Password' => $this->password
        ];
        $payload[$type]['ClientDetail'] = [
            'AccountNumber' => $this->account_number,
            'MeterNumber' => $this->meter_number
        ];
        return $payload;
    }

    private function fedexXMLFile($data)
    {
        $xml = new SimpleXMLElement('<SOAP-ENV:Envelope><SOAP-ENV:Body></SOAP-ENV:Body></SOAP-ENV:Envelope>', LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope');
        $xml->addAttribute('xmlns', 'http://fedex.com/ws/ship/v21');

        $body = array_to_xml($data,new SimpleXMLElement('<SOAP-ENV:Body></SOAP-ENV:Body>', LIBXML_NOERROR, false, 'ws', true));

        $xml->addChild("item","Test");


        dd($xml->asXML());
        $itemsNode->addChild("item", $newValue);
        $sxe->asXML("myxml.xml"); 

        
    }

    private function call($type, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fedexXMLFile($type, $data)->asXML());
        $result = curl_exec($ch);
        curl_error($ch);

        return XMLToArray($result);
    }
}
