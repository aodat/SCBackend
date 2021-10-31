<?php 
namespace Libs;

use Carbon\Carbon;

use Mtc\Dhl\Client\Web;
use Mtc\Dhl\Entity\GB\BookPURequest;

class DHL
{
    private $config;
    private $account_number;
    function __construct() {
        $this->config = [
            'MessageTime' => Carbon::today(),
            'MessageReference' => mt_rand(1000000000, 9999999999),
            'SiteID' => config('carriers.dhl.SITE_ID'),
            'Password' => config('carriers.dhl.PASSWORD')
        ];
        
        $this->account_number = config('carriers.dhl.ACCOUNT_NUMBER');
    }
    public function createPickup($email,$date,$address)
    {
        
        $pickup = new BookPURequest();
        // Setup Config
        $pickup->SiteID = $this->config['SiteID'];
        $pickup->Password = $this->config['Password'];
        $pickup->MessageTime = Carbon::now()->format(Carbon::ATOM);
        $pickup->MessageReference = '12345678912345678912345678912345';
        $pickup->SoftwareName = 'XMLPI';
        $pickup->SoftwareVersion = '3.0';
        $pickup->Requestor->AccountNumber = '951237760';
        $pickup->Requestor->AccountType = 'D';


        /*
            array:10 [
                "id" => 1
                "area" => "Tabrbour Amman"
                "city" => "Amman"
                "name" => "Tareq Fawakhiri"
                "phone" => "07999999"
                "country" => "Jordan"
                "city_code" => "Amman"
                "created_at" => "2021-10-31T13:52:23.036546Z"
                "description" => "test test"
                "country_code" => "JO"
            ]
        */

        // $payload['Pickup']['Reference1'] = $address['description'];
        // $payload['Pickup']['PickupAddress']['Line1'] = $address['description'];
        // $payload['Pickup']['PickupAddress']['Line2'] = $address['area'];
        // $payload['Pickup']['PickupAddress']['Line3'] = '';
        // $payload['Pickup']['PickupAddress']['City'] = $address['name_en'];
        // $payload['Pickup']['PickupAddress']['CountryCode'] = $address['country_code'];

        // $payload['Pickup']['PickupContact']['PersonName'] = $address['name_en'];
        // $payload['Pickup']['PickupContact']['CompanyName'] = $address['name_en'];
        // $payload['Pickup']['PickupContact']['PhoneNumber1'] = $address['phone'];
        // $payload['Pickup']['PickupContact']['CellPhone'] = $address['phone'];
        // $payload['Pickup']['PickupContact']['EmailAddress'] = $email;

        // $payload['Pickup']['PickupLocation'] = $address['city_code'];
        // $payload['Pickup']['PickupDate'] = '/Date('.(strtotime($date) * 1000).')/';

        // $ReadyTime  = strtotime(Carbon::createFromFormat('Y-m-d',$date)->format('Y-m-d').' 03:00 PM') * 1000;
        // $LastPickupTime = $ClosingTime = strtotime(Carbon::createFromFormat('Y-m-d',$date)->format('Y-m-d').' 04:00 PM') * 1000;

        // $payload['Pickup']['ReadyTime'] = '/Date('.$ReadyTime.')/';
        // $payload['Pickup']['LastPickupTime'] = '/Date('.$LastPickupTime.')/';
        // $payload['Pickup']['ClosingTime'] = '/Date('.$ClosingTime.')/';

        $pickup->Requestor->CompanyName =  $address['name'];
        $pickup->Requestor->RequestorContact->PersonName = $address['name'];
        $pickup->Requestor->RequestorContact->Phone = $address['phone'];

        // DHL REGION based on the Toolkit documentation for the pickup country
        $pickup->RegionCode = 'AP';// $dhl_region;

        $pickup->Place->LocationType = 'B';
        $pickup->Place->CompanyName = $address['name'];
        $pickup->Place->Address1 = $address['city'];
        $pickup->Place->Address2 =$address['city'];
        $pickup->Place->PackageLocation = '';
        $pickup->Place->City = $address['city'];
        $pickup->Place->CountryCode = $address['country_code'];
        $pickup->Place->PostalCode = '';

        $pickup->Pickup->PickupDate = '2021-11-11';// $pickup_time;
        $pickup->Pickup->ReadyByTime = '13:00';
        $pickup->Pickup->CloseTime = '17:00';

        $pickup->PickupContact->PersonName = $address['name'];
        $pickup->PickupContact->Phone = $address['phone'];

        $pickup->ShipmentDetails->AccountType = 'D';
        $pickup->ShipmentDetails->AccountNumber = '951237760';
        $pickup->ShipmentDetails->BillToAccountNumber = '951237760';

        $pickup->ShipmentDetails->AWBNumber = 123;// $tracking_number;
        $pickup->ShipmentDetails->NumberOfPieces = 1;// $package_count;
        $pickup->ShipmentDetails->GlobalProductCode = 1;// $shipment_product_code;
        $pickup->ShipmentDetails->Weight = 1;// $package_weight;
        $pickup->ShipmentDetails->WeightUnit = 'K';
        $pickup->ShipmentDetails->DoorTo = 'DD';
        $pickup->ShipmentDetails->DimensionUnit = 'C';

        $client = new Web();
        $xml_response = $client->call($pickup);
        echo ($xml_response); die;

        // $payload = $this->bindJsonFile('pickup.create.json');
        // $payload['req:BookPURequest']['Request']['ServiceHeader'] = $this->config;



        // echo json_encode($payload);die;
        // dd($payload);
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