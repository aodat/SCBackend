<?php
namespace App\Traits;

use Libs\Aramex;
use Libs\Stripe;
use Libs\DHL;
use Libs\Fedex;

use App\Exceptions\CarriersException;

use App\Models\Merchant;

trait CarriersManager {
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider,$isWebHook = false) {
        $provider = strtoupper($provider);
        switch($provider)
        {
            case "ARAMEX":
                $this->adapter = new Aramex();
                break;
            case "DHL":
                $this->adapter = new DHL();
                break;
            case "FEDEX":
                $this->adapter = new Fedex();
                break;
            case "STRIPE":
                $this->adapter = new Stripe();
                break;
            default:
                throw new CarriersException('Invalid Provider');
        }

        if(!$isWebHook)
            $this->merchantInfo = $this->getMerchantInfo();
    } 
    
    public function getMerchantInfo()
    {
        return Merchant::findOrFail(Request()->user()->merchant_id);
    }

    public function generateShipment($provider,$merchantInfo = null ,$shipmentArray)
    {
        $this->loadProvider($provider);
        $shipments = $this->adapter->createShipment($merchantInfo,$shipmentArray);

        return [
            'link' => collect($shipments)->pluck('file'),
            'id' => ($provider == 'Aramex') ? collect($shipments)->pluck('id') : $shipments['id']
        ];
    }

    public function generateShipmentArray($provider,$shipmentInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->shipmentArray($this->merchantInfo,$shipmentInfo);
    }

    public function generatePickup($provider,$pickup_date,$address)
    {
        $this->loadProvider($provider);
        return $this->adapter->createPickup($this->merchantInfo->email,$pickup_date,$address);
    }

    public function printShipment($provider,$shipments_number)
    {
        $this->loadProvider($provider);
        return mergePDF($this->adapter->printLabel($shipments_number));
    }

    public function cancelPickup($provider,$pickupInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->cancelPickup($pickupInfo);
    }

    public function track($provider,$shipments_number)
    {
        $this->loadProvider($provider);
        return $this->adapter->trackShipment($shipments_number);
    }

    public function calculateFees($provider,$carrier_id,$country_code,$weight)
    {
        $this->loadProvider($provider);
        $provider = strtoupper($provider);
        $express_rates =  collect(
                            json_decode(file_get_contents(storage_path().'/../App/Libs/express.rates.json'),true)['Countries']
                        )->where('code',$country_code)
                        ->all();

        if(count($express_rates) > 1)
            throw new CarriersException('Express Rates Json Retrun More Than One Country');
        
        $list = reset($express_rates);
        
        $zones = collect($list['zones'])->where('carrier_id',$carrier_id)->all();

        if(count($zones) > 1)
            throw new CarriersException('Express Rates Json Retrun More Than One zone');

        $zone_id = reset($zones)['zone_id'];
        
        $zoneRates = collect($this->merchantInfo['express_rates'][$carrier_id]['zones'])->where('id',$zone_id)->all();

        if(count($zoneRates) != 1)
            throw new CarriersException('Express Rates Json Retrun More Than One Zone In User Merchant ID');

        $zoneRates = reset($zoneRates);
        $base = $zoneRates['basic'];
        $additional = $zoneRates['additional'];

        $fees = 0;
        if ($weight > 0) {
            $weights_count = ceil($weight / 0.5);
            $weight_fees = (($weights_count - 1) * $additional) + $base;
            $fees += $weight_fees;
        }
        
        if($fees == 0)
            throw new CarriersException('Fees Equal Zero');

        return $fees;
    }

    public function webhook($shipmentInfo,$status)
    {
        $this->loadProvider($shipmentInfo['provider'],true);

        
        $ChargeableWeight = $this->adapter->trackShipment([$shipmentInfo['external_awb']],true)['ChargeableWeight'] ?? null;

        // if($ChargeableWeight)
        //     throw new CarriersException('Chargeable Weight Is Zero');

        $setup = $this->adapter->setup[$status] ?? ['status' => 'PROCESSING'];
        if($ChargeableWeight) 
            $setup['chargable_weight'] = $this->calculateFees($shipmentInfo['provider'],$shipmentInfo['carrier_id'],$shipmentInfo['consignee_country'],$ChargeableWeight);

        $shipmentInfo->update($setup);
        return true;
    }

    public function invoice($data)
    {
        $this->loadProvider('Stripe');
        $customerID = $this->adapter->createCustomer($data['customer_name'],$data['customer_email']);
        return $this->adapter->invoice($customerID,$data['description'],$data['amount']);
    }

    public function publishInvoice($invoiceID)
    {
        $this->loadProvider('Stripe');
        return $this->adapter->finalizeInvoice($invoiceID);
    }

    public function deleteInvoice($invoiceID)
    {
        $this->loadProvider('Stripe');
        return $this->adapter->deleteInvoice($invoiceID);
    }
}