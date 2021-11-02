<?php 
namespace Libs;

use Carbon\Carbon;

use Mtc\Dhl\Client\Web;
use Mtc\Dhl\Entity\GB\BookPURequest;

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

        $payload->PickupContact->PersonName = "Kosani";// $address['name'];
        $payload->PickupContact->Phone = "1234567890";// $address['phone'];

        $payload->ShipmentDetails->AccountType = 'D';
        $payload->ShipmentDetails->AccountNumber = $this->config['AccountNumber'];
        $payload->ShipmentDetails->BillToAccountNumber = $this->config['AccountNumber'];
        $payload->ShipmentDetails->AWBNumber = "7520067111";// $tracking_number;
        $payload->ShipmentDetails->NumberOfPieces = 1;// $package_count;
        $payload->ShipmentDetails->GlobalProductCode = "P";// $shipment_product_code;
        $payload->ShipmentDetails->Weight = 10;// $package_weight;
        $payload->ShipmentDetails->WeightUnit = 'K';
        $payload->ShipmentDetails->DoorTo = 'DD';
        $payload->ShipmentDetails->DimensionUnit = 'C';

        // Call DHL API
        $client = new Web("production");
        $response = XMLToArray($client->call($payload));
        
        if ($response['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');

        // return ['id' => $final['ProcessedPickup']['ID'] , 'guid' => $final['ProcessedPickup']['GUID']];
    }
    public function cancelPickup()
    {
        $payload = $this->bindJsonFile('pickup.cancel.json');

        $payload['req:CancelPURequest']['Request']['ServiceHeader'] = $this->config;
        $payload['req:CancelPURequest']['RegionCode'] = '';
        $payload['req:CancelPURequest']['ConfirmationNumber'] = '';
        $payload['req:CancelPURequest']['RequestorName'] = '';
        $payload['req:CancelPURequest']['CountryCode'] = '';
        $payload['req:CancelPURequest']['OriginSvcArea'] = '';
        $payload['req:CancelPURequest']['Reason'] = '';
        $payload['req:CancelPURequest']['PickupDate'] = '';
        $payload['req:CancelPURequest']['CancelTime'] = '';
        
        dd($payload);
    }
    public function printLabel(){}
    public function createShipment(){}
    public function shipmentArray(){}
    
    public function bindJsonFile($file)
    {
        return json_decode(file_get_contents(storage_path().'/../App/Libs/DHL/'.$file),true);
    }
}