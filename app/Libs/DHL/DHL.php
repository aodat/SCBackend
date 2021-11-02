<?php 
namespace Libs;

use Carbon\Carbon;

use Mtc\Dhl\Client\Web;

use Mtc\Dhl\Entity\GB\BookPURequest;
use Mtc\Dhl\Entity\GB\ShipmentRequest;
use Mtc\Dhl\Entity\GB\CancelPickupRequest;
// use Mtc\Dhl\Entity\
use Mtc\Dhl\Datatype\GB\Piece;


use App\Exceptions\CarriersException;

class DHL
{
    private $config;
    function __construct() {
        $this->config = [
            'MessageTime' => Carbon::now()->format(Carbon::ATOM),
            'MessageReference' => randomNumber(32),
            'SiteID' => config('carriers.dhl.SITE_ID'),
            'Password' =>  config('carriers.dhl.PASSWORD'),
            'AccountNumber' => config('carriers.dhl.ACCOUNT_NUMBER')
        ];
    }
    public function createPickup($email,$date,$address)
    {

        $payload = new BookPURequest();

        // Setup Config
        $payload->SiteID = $this->config['SiteID'];
        $payload->Password = $this->config['Password'];
        $payload->MessageTime = $this->config['MessageTime'];
        $payload->MessageReference = $this->config['MessageReference'];
        $payload->SoftwareName = 'XMLPI';
        $payload->SoftwareVersion = '3.0';

        // Requester 
        $payload->Requestor->AccountNumber = $this->config['AccountNumber'];
        $payload->Requestor->AccountType = 'D';
        $payload->Requestor->CompanyName ="DHL TEST";// $address['name'];
        $payload->Requestor->Address1 ="DHL EXPRESS FR";// $address['name'];
        $payload->Requestor->City ="Paris";// $address['name'];
        $payload->Requestor->CountryCode ="FR";// $address['name'];
        $payload->Requestor->PostalCode ="75001";// $address['name'];
        $payload->Requestor->RequestorContact->PersonName = "Roy";// $address['name'];
        $payload->Requestor->RequestorContact->Phone = "1234567890";// $address['phone'];


        // DHL REGION based on the Toolkit documentation for the pickup country
        $payload->RegionCode = 'AP';

        // Places 
        $payload->Place->LocationType = 'B';
        $payload->Place->CompanyName = "Test Pickup";// $address['name'];
        $payload->Place->Address1 = "DHL EXPRESS GB";// $address['city'];
        $payload->Place->Address2 = "A Road";// $address['city'];
        $payload->Place->PackageLocation = "Reception";// $address['city'];
        $payload->Place->City = "LIVERPOOL";// $address['city'];
        $payload->Place->CountryCode = "GB";// $address['country_code'];
        $payload->Place->PostalCode = "L24 8RF";

        // Pickup
        $payload->Pickup->PickupDate = '2021-11-05';
        $payload->Pickup->PickupTypeCode = 'A';
        $payload->Pickup->ReadyByTime = '10:20';
        $payload->Pickup->CloseTime = '14:20';
        $payload->Pickup->RemotePickupFlag = 'Y';
        $payload->PickupContact->PersonName = "Kosani";// $address['name'];
        $payload->PickupContact->Phone = "1234567890";// $address['phone'];

        $payload->ShipmentDetails->AccountType = 'D';
        $payload->ShipmentDetails->AccountNumber = $this->config['AccountNumber'];
        $payload->ShipmentDetails->BillToAccountNumber = $this->config['AccountNumber'];
        $payload->ShipmentDetails->AWBNumber = "7520067111";// $tracking_number;
        $payload->ShipmentDetails->NumberOfPieces = 1;// $package_count;
        $payload->ShipmentDetails->GlobalProductCode = "P";// $payload_product_code;
        $payload->ShipmentDetails->Weight = 10;// $package_weight;
        $payload->ShipmentDetails->WeightUnit = 'K';
        $payload->ShipmentDetails->DoorTo = 'DD';
        $payload->ShipmentDetails->DimensionUnit = 'C';

        $payload->Consignee->CompanyName = 'Test Pickup';
        $payload->Consignee->AddressLine = 'DHL EXPRESS GB';
        $payload->Consignee->City = 'LIVERPOOL';
        $payload->Consignee->CountryCode = 'FR';
        $payload->Consignee->PostalCode = '75001';
        $payload->Consignee->Contact->PersonName = 'Tareq FW';
        $payload->Consignee->Contact->Phone = '12345';

        echo $payload->toXML();die;
        // Call DHL API
        $client = new Web();
        $response = XMLToArray($client->call($payload));
        dd($response);

        if ($response['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');

    }

    public function cancelPickup()
    {
        $payload = new CancelPickupRequest();
        $payload->SiteID = $this->config['SiteID'];
        $payload->Password = $this->config['Password'];
        $payload->MessageTime = $this->config['MessageTime'];
        $payload->MessageReference = $this->config['MessageReference'];
        $payload->SoftwareName = 'XMLPI';
        $payload->SoftwareVersion = '3.0';

        $payload->RegionCode = "AM";
        $payload->ConfirmationNumber = "CBJ180206002254";
        $payload->RequestorName = "Roy";
        $payload->CountryCode = "CA";
        $payload->OriginSvcArea = "YHM";
        $payload->Reason = '001';
        $payload->PickupDate = '2017-11-21';
        $payload->CancelTime = '10:20';

        $client = new Web();
        $response = XMLToArray($client->call($payload));
        
        if ($response['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');
        dd($response);
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

        $payload->Billing->ShipperAccountNumber = $this->config['AccountNumber'];
        $payload->Billing->ShippingPaymentType = 'S';
        $payload->Billing->BillingAccountNumber = $this->config['AccountNumber'];

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
        $payload->Shipper->RegisteredAccount = $this->config['AccountNumber'];;
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
}