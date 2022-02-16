<?php

namespace App\Traits;

use App\Exceptions\CarriersException;
use App\Http\Controllers\Utilities\Documents;
use App\Http\Controllers\Utilities\InvoiceService;
use App\Models\Carriers;
use App\Models\Merchant;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Libs\Aramex;
use Libs\DHL;
use Libs\Fedex;

trait CarriersManager
{
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider, $settings = array(), $getMerchant = true)
    {
        $provider = strtoupper($provider);
        if (empty($settings)) {
            $settings = Carriers::where('name', $provider)->first()->env ?? null;
        }

        switch ($provider) {
            case "ARAMEX":
                $this->adapter = new Aramex($settings);
                break;
            case "DHL":
                $this->adapter = new DHL($settings);
                break;
            case "FEDEX":
                $this->adapter = new Fedex($settings);
                break;
            default:
                throw new CarriersException('Invalid Provider');
        }

        if ($getMerchant) {
            $this->merchantInfo = $this->getMerchantInfo();
        }

    }

    public function getMerchantInfo()
    {
        if (Request()->user() === null) {
            return Merchant::findOrFail(1);
        } else {
            return App::make('merchantInfo');
        }

    }

    public function check($provider, $settings)
    {
        $this->loadProvider($provider, $settings);
        $this->adapter->validate($this->merchantInfo);
    }

    public function generateShipment($provider, $merchantInfo = null, $shipmentArray)
    {
        $this->loadProvider($provider);
        $shipments = $this->adapter->createShipment($merchantInfo, $shipmentArray);
        return [
            'link' => (isset($shipments[0])) ? collect($shipments)->pluck('file')->toArray() : $shipments['file'],
            'id' => (isset($shipments[0])) ? collect($shipments)->pluck('id') : $shipments['id'],
        ];
    }

    public function generateShipmentArray($provider, $shipmentInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->shipmentArray($this->merchantInfo, $shipmentInfo);
    }

    public function generatePickup($provider, $pickInfo, $address)
    {
        $this->loadProvider($provider);
        return $this->adapter->createPickup($this->merchantInfo->email, $pickInfo, $address);
    }

    public function printShipment($shipments_number)
    {
        $shipments = DB::table('shipments')
            ->whereIn('awb', $shipments_number)
            ->get();

        $exported = [];
        $shipments->map(function ($shipment) use (&$exported) {
            $exported[] = $shipment->url;
            if ($shipment->group == 'EXP' && !$shipment->is_doc) {
                $exported[] = InvoiceService::commercial($shipment);
            }

        });
        
        return Documents::merge($exported);
    }

    public function cancelPickup($provider, $pickupInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->cancelPickup($pickupInfo);
    }

    public function track($provider, $shipments_number, $all = false)
    {
        $this->loadProvider($provider, [], false);
        return $this->adapter->trackShipment($shipments_number, $all);
    }
}
