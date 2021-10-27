<?php
namespace App\Traits;

use Libs\Aramex;
use Libs\DHL;
use Libs\Fedex;
use Libs\Strip;

use App\Exceptions\CarriersException;

use App\Models\Merchant;
use App\Models\Shipment;

trait CarriersManager {
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider) {
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
            case "STRIP":
                $this->adapter = new Strip();
                break;
            default:
                throw new CarriersException('Invalid Provider');
        }
        $this->merchantInfo = $this->getMerchantInfo();
    } 
    
    public function getMerchantInfo()
    {
        return Merchant::findOrFail(Request()->user()->merchant_id);
    }

    public function generateShipment($provider,$shipmentArray)
    {
        $this->loadProvider($provider);
        $shipments = $this->adapter->createShipment($shipmentArray);
        
        $link = mergePDF((collect($shipments)->pluck('file')));

        return [
            'link' => $link,
            'id' => collect($shipments)->pluck('id')
        ];
    }

    public function generateShipmentArray($provider,$address,$shipmentInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->shipmentArray($this->merchantInfo,$address,$shipmentInfo);
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

    public function cancelPickup($provider,$shipment_number)
    {
        $this->loadProvider($provider);
        return $this->adapter->cancelPickup($shipment_number);
    }

    public function calculateFees($provider)
    {
        $this->loadProvider($provider);
        // $shipments = $this->adapter->createShipment($shipmentArray);
    }
}