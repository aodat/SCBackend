<?php

namespace App\Traits;

use Libs\Aramex;
use Libs\DHL;
use Libs\Fedex;

use App\Exceptions\CarriersException;
use App\Models\Country;
use Illuminate\Support\Facades\App;

trait CarriersManager
{
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider, $isWebHook = false)
    {
        $provider = strtoupper($provider);
        switch ($provider) {
            case "ARAMEX":
                $this->adapter = new Aramex();
                break;
            case "DHL":
                $this->adapter = new DHL();
                break;
            case "FEDEX":
                $this->adapter = new Fedex();
                break;
            default:
                throw new CarriersException('Invalid Provider');
        }

        if (!$isWebHook)
            $this->merchantInfo = $this->getMerchantInfo();
    }

    public function getMerchantInfo()
    {
        return App::make('merchantInfo');
    }

    public function generateShipment($provider, $merchantInfo = null, $shipmentArray)
    {
        $this->loadProvider($provider);
        $shipments = $this->adapter->createShipment($merchantInfo, $shipmentArray);

        return [
            'link' => collect($shipments)->pluck('file'),
            'id' => ($provider == 'Aramex') ? collect($shipments)->pluck('id') : $shipments['id']
        ];
    }

    public function generateShipmentArray($provider, $shipmentInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->shipmentArray($this->merchantInfo, $shipmentInfo);
    }

    public function generatePickup($provider, $pickup_date, $address)
    {
        $this->loadProvider($provider);
        return $this->adapter->createPickup($this->merchantInfo->email, $pickup_date, $address);
    }

    public function printShipment($provider, $shipments_number)
    {
        $this->loadProvider($provider);
        return mergePDF($this->adapter->printLabel($shipments_number));
    }

    public function cancelPickup($provider, $pickupInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->cancelPickup($pickupInfo);
    }

    public function track($provider, $shipments_number)
    {
        $this->loadProvider($provider);
        return $this->adapter->trackShipment($shipments_number);
    }

    /*
        $type : DOM , Express
    */
    public function calculateFees($carrier_id, $country_code, $type, $weight)
    {
        return rand(1,20);
        $this->merchantInfo = $this->getMerchantInfo();
        if ($type == 'DOM') {
            $rate = collect($this->merchantInfo['domestic_rates'][$carrier_id])->where('code', $country_code);

            if ($rate->isEmpty())
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');

            $price = $rate->first()->price;
            $fees = ceil($weight / 10) * $price;
        } else {
            $express_rates = collect(Country::where('code', $this->merchantInfo->country_code)->first());
            if ($express_rates->isEmpty())
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');

            $express_rates = $express_rates->rates;
            if (!isset($express_rates[$country_code]))
                throw new CarriersException('No Setup Added To This Country, Please Contact Administrators');

            $rates = collect($express_rates[$country_code]);
            $zones = $rates->where('carrier_id', $carrier_id);

            if ($zones->count() > 1)
                throw new CarriersException('Somthing Wrong On Rates Setup, Please Contact Administrators');

            if (!isset($zones->first()['zone_id']))
                throw new CarriersException('Somthing Wrong On Zone ID Setup, Please Contact Administrators');

            $zone_id = $zones->first()['zone_id'];
            $zoneRates = collect($this->merchantInfo['express_rates'][$carrier_id]['zones'])->where('id', $zone_id);
            if ($zoneRates->count() > 1)
                throw new CarriersException('Express Rates Json Retrun More Than One Zone In User Merchant ID');

            $zoneRates = $zoneRates->first();
            $base = $zoneRates['basic'];
            $additional = $zoneRates['additional'];

            $fees = 0;
            if ($weight > 0) {
                $weights_count = ceil($weight / 0.5);
                $weight_fees = (($weights_count - 1) * $additional) + $base;
                $fees += $weight_fees;
            }
        }

        if ($fees == 0)
            throw new CarriersException('Fees Equal Zero');

        return $fees;
    }

    public function webhook($shipmentInfo, $status)
    {
        $this->loadProvider($shipmentInfo['provider'], true);
        $ChargeableWeight = $this->adapter->trackShipment([$shipmentInfo['external_awb']], true)['ChargeableWeight'] ?? null;

        // if($ChargeableWeight)
        //     throw new CarriersException('Chargeable Weight Is Zero');

        $setup = $this->adapter->setup[$status] ?? ['status' => 'PROCESSING'];
        if ($ChargeableWeight)
            $setup['chargable_weight'] = $this->calculateFees(
                $shipmentInfo['provider'],
                $shipmentInfo['carrier_id'],
                $shipmentInfo['consignee_country'],
                $shipmentInfo['group'],
                $ChargeableWeight
            );

        $shipmentInfo->update($setup);
        return true;
    }
}
