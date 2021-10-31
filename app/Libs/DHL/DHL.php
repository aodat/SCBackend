<?php 
namespace Libs;

use Carbon\Carbon;
use Stripe\Util\RandomGenerator;

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
        $payload = $this->bindJsonFile('pickup.create.json');
        $payload['req:BookPURequest']['Request']['ServiceHeader'] = $this->config;
        echo json_encode($payload);die;
        dd($payload);
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