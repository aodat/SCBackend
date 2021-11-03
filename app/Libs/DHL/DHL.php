<?php 
namespace Libs;

use Carbon\Carbon;

use Mtc\Dhl\Client\Web;

use Mtc\Dhl\Entity\GB\BookPURequest;
use Mtc\Dhl\Entity\GB\ShipmentRequest;
use Mtc\Dhl\Entity\GB\CancelPickupRequest;
use Mtc\Dhl\Datatype\GB\Piece;


use Illuminate\Support\Facades\Http;

use App\Exceptions\CarriersException;
use App\Models\Merchant;
use App\Models\Pickup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;
use SimpleXMLElement;

class DHL
{
    private static $xsd = [
        'BookPURequest' => 'http://www.dhl.com book-pickup-global-req_EA.xsd',
        'CancelPURequest' => 'http://www.dhl.com cancel-pickup-global-req.xsd'
    ];

    private static $stagingUrl = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
    private static $productionUrl = 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';

    private $end_point;
    private $account_number;
    function __construct() {
        $this->config = [
            'MessageTime' => Carbon::now()->format(Carbon::ATOM),
            'MessageReference' => randomNumber(32),
            'SiteID' => config('carriers.dhl.SITE_ID'),
            'Password' =>  config('carriers.dhl.PASSWORD')
        ];

        $this->end_point = self::$stagingUrl;
        $this->account_number = config('carriers.dhl.ACCOUNT_NUMBER');

    }
    
    public function createPickup($email,$date,$address)
    {
        $payload = $this->bindJsonFile('pickup.create.json');
        
        $payload['Requestor']['AccountNumber'] = $this->account_number;

        $payload['Requestor']['CompanyName'] =  $address['name'];
        $payload['Requestor']['Address1'] = $address['name'];
        $payload['Requestor']['City'] = $address['name'];
        $payload['Requestor']['CountryCode'] = $address['country_code'];
        $payload['Requestor']['PostalCode'] = $address['name'];
        $payload['Requestor']['RequestorContact']['PersonName'] = $address['name'];
        $payload['Requestor']['RequestorContact']['Phone'] =  $address['phone'];
    
        $payload['Place']['CompanyName'] = $address['name'];
        $payload['Place']['Address1'] = $address['city'];
        $payload['Place']['Address2'] = $address['city'];
        $payload['Place']['PackageLocation'] = $address['city'];
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

        $payload['ConsigneeDetails']['CompanyName'] = $address['name'];
        $payload['ConsigneeDetails']['AddressLine'] = $address['area'];
        $payload['ConsigneeDetails']['City'] = $address['city'];
        $payload['ConsigneeDetails']['CountryCode'] =  $address['country_code'];
        $payload['ConsigneeDetails']['PostalCode'] = '';
        $payload['ConsigneeDetails']['Contact']['PersonName'] = $address['name'];
        $payload['ConsigneeDetails']['Contact']['Phone'] = $address['phone'];

        $response = $this->call('BookPURequest',$payload);
        
        if(isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');
        return ['id' => $this->config['MessageReference'] , 'guid' => $response['ConfirmationNumber']];
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

        $response = $this->call('CancelPURequest',$payload);
        if(isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Cancel Pickup – Something Went Wrong');

        return true;
    }

    public function printLabel(){}

    public function createShipment($merchentInfo,$address,$shipmentInfo)
    {
        $payload = new ShipmentRequest();
        $payload->SiteID = $this->config['SiteID'];
        $payload->Password = $this->config['Password'];
        $payload->MessageTime = $this->config['MessageTime'];
        $payload->MessageReference = $this->config['MessageReference'];
        $payload->SoftwareName = 'XMLPI';
        $payload->SoftwareVersion = '3.0';


        $payload->LanguageCode = 'en';
        $payload->PiecesEnabled = 'Y';

        $payload->Billing->ShipperAccountNumber = $this->account_number;
        $payload->Billing->ShippingPaymentType = 'S';
        $payload->Billing->BillingAccountNumber = $this->account_number;

        $payload->Consignee->CompanyName = $shipmentInfo['consignee_name'];
        $payload->Consignee->Contact->PersonName = $shipmentInfo['consignee_name'];
        $payload->Consignee->addAddressLine($shipmentInfo['consignee_address_description']);
        $payload->Consignee->addAddressLine($shipmentInfo['consignee_address_description']);
        $payload->Consignee->City = $shipmentInfo['consignee_city'];
        $payload->Consignee->PostalCode = $shipmentInfo['consignee_zip_code'] ?? '';
        $payload->Consignee->Division = '';
        $payload->Consignee->CountryCode = $shipmentInfo['consignee_country'];
        $payload->Consignee->CountryName = $shipmentInfo['consignee_country'];
        $payload->Consignee->Contact->PhoneNumber = $shipmentInfo['consignee_phone'];
        $payload->Consignee->Contact->Email = $shipmentInfo['consignee_email'];
        $payload->Consignee->Contact->PhoneExtension = '';
        $payload->Consignee->Contact->FaxNumber = '';
        $payload->Consignee->Contact->Telex = '';


        $payload->RegionCode = 'AM';

        $payload->Dutiable->DeclaredValue = 1.0;
        $payload->Dutiable->DeclaredCurrency = 'GBP';
        $payload->Dutiable->TermsOfTrade = 'DDP';

        $payload->Shipper->ShipperID = $merchentInfo->id;
        $payload->Shipper->RegisteredAccount = $this->account_number;;
        $payload->Shipper->CompanyName = $address['name'];
        $payload->Shipper->addAddressLine($address['description']);
        $payload->Shipper->addAddressLine($address['area']);
        $payload->Shipper->City = $address['city_code'];
        $payload->Shipper->PostalCode = '';
        $payload->Shipper->CountryCode =  $merchentInfo->country_code;
        $payload->Shipper->CountryName = $merchentInfo->country_code;
        $payload->Shipper->Contact->PersonName = $address['name'];
        $payload->Shipper->Contact->PhoneNumber = $address['phone'];
        $payload->Shipper->Contact->Email = $merchentInfo->email;
        $payload->Shipper->Contact->PhoneExtension = '';
        $payload->Shipper->Contact->FaxNumber = '';
        $payload->Shipper->Contact->Telex = '';

        
        // Delivery Service is obtained via Quote request which will find valid services for shipment
        $payload->ShipmentDetails->GlobalProductCode = "P";
        $payload->ShipmentDetails->Contents = $shipmentInfo['content'];
        $payload->ShipmentDetails->CurrencyCode = 'GBP';
        $payload->ShipmentDetails->WeightUnit = 'K';
        $payload->ShipmentDetails->Weight = 1;
        $payload->ShipmentDetails->Date = Carbon::now()->format('Y-m-d');
        $payload->ShipmentDetails->DimensionUnit = 'C';
        $payload->ShipmentDetails->InsuredAmount = 0;
        $payload->ShipmentDetails->PackageType = 'PA';
        $payload->ShipmentDetails->IsDutiable = 'N';
        $payload->ShipmentDetails->DOSFlag = 'N';

        // Information about Packages in shipment
        $payload->ShipmentDetails->NumberOfPieces = 1;
        
        $piece = new Piece();
        $piece->PieceID = 1;
        $piece->PackageType = 'CP';
        $piece->Weight = 0.5;
        $piece->DimWeight = 0.5;
        $payload->ShipmentDetails->addPiece($piece);

        $payload->EProcShip = 'N';
        $payload->LabelImageFormat = 'PDF';
        $payload->Label->LabelTemplate = '8X4_PDF';

        $client = new Web();
        $response = XMLToArray($client->call($payload));
        
        if ($response['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');
        dd($response);
    }

    public function shipmentArray($merchentInfo,$address,$shipmentInfo){
        return $this->createShipment($merchentInfo,$address,$shipmentInfo);
    }

    public function bindJsonFile($file)
    {
        $payload = json_decode(file_get_contents(storage_path().'/../App/Libs/DHL/'.$file),true);
        $payload['Request']['ServiceHeader'] = $this->config;

        return $payload;
    }

    private function dhlXMLFile($type,$data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'."<req:$type></req:$type>", LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('req:xmlns:req', 'http://www.dhl.com');
        $xml->addAttribute('req:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->addAttribute('req:xsi:schemaLocation', self::$xsd[$type]);
        $xml->addAttribute('req:schemaVersion', '3.0');
        return array_to_xml($data,$xml);
    }

    private function call($type,$data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->dhlXMLFile($type,$data)->asXML());
        $result = curl_exec($ch);
        curl_error($ch);

        return XMLToArray($result);
    }
}