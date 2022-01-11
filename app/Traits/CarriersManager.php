<?php

namespace App\Traits;

use Libs\Aramex;
use Libs\DHL;
use Libs\Fedex;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Utilities\InvoiceService;
use App\Exceptions\CarriersException;

use App\Models\Country;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
use Carbon\Carbon;

trait CarriersManager
{
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider, $merchantID = null)
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

        $this->merchantInfo = $this->getMerchantInfo($merchantID);
    }

    public function getMerchantInfo($merchantID = null)
    {
        if ($merchantID)
            return Merchant::findOrFail($merchantID);
        else
            return App::make('merchantInfo');
    }

    public function generateShipment($provider, $merchantInfo = null, $shipmentArray)
    {
        $this->loadProvider($provider);
        $shipments = $this->adapter->createShipment($merchantInfo, $shipmentArray);
        return [
            'link' => (isset($shipments[0])) ? collect($shipments)->pluck('file')->toArray() : $shipments['file'],
            'id' => (isset($shipments[0])) ? collect($shipments)->pluck('id') : $shipments['id']
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

    public function printShipment($shipments_number)
    {
        $shipments = Shipment::whereIn('external_awb', $shipments_number)->get();
        $shipments = $shipments->map(function ($shipment) {
            if ($shipment['group'] == 'EXP' && !$shipment['is_doc']) {
                $shipment['url'] = mergePDF([InvoiceService::commercial($shipment), $shipment['url']]);
            }
            return $shipment;
        });
        return mergePDF($shipments->pluck('url'));
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

    public function calculateFees($carrier_id, $from = null, $to, $type, $weight)
    {
        $this->merchantInfo = Request()->user() === null ? collect(App::make('merchantInfo')) : $this->getMerchantInfo();
        if ($type == 'domestic' || $type == 'DOM') {
            if (!isset($this->merchantInfo['domestic_rates'][$carrier_id]))
                throw new CarriersException('The Carrier ID ' . $carrier_id . ' No Support domestic , Please Contact Administrators');

            $rate = collect($this->merchantInfo['domestic_rates'][$carrier_id])->where('code', $to);

            if ($rate->isEmpty())
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');

            $price = $rate->first()['price'];
            $fees = ceil($weight / 10) * $price;
        } else {
            $express_rates = collect(Country::where('code', $this->merchantInfo['country_code'])->first());
            if ($express_rates->isEmpty())
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');

            $express_rates = $express_rates['rates'];
            if (count($express_rates) == 0)
                throw new CarriersException('No Setup Added To This Country, Please Contact Administrators');

            if (!isset($express_rates[$to]))
                throw new CarriersException('No Setup Added To This Country, Please Contact Administrators');

            $rates = collect($express_rates[$to]);
            $zones = $rates->where('carrier_id', $carrier_id);

            if ($zones->count() > 1)
                throw new CarriersException('Somthing Wrong On Rates Setup, Please Contact Administrators');

            if (!isset($zones->first()['zone_id']))
                throw new CarriersException('Somthing Wrong On Zone ID Setup, Please Contact Administrators');

            $zone_id = $zones->first()['zone_id'];
            $discounts = $this->merchantInfo['express_rates'][$carrier_id]['discounts'] ?? [];

            $zoneRates = collect($this->merchantInfo['express_rates'][$carrier_id]['zones'])->where('id', $zone_id);
            if ($zoneRates->count() > 1)
                throw new CarriersException('Express Rates Json Retrun More Than One Zone In User Merchant ID');

            $zoneRates = $zoneRates->first();
            if ($zoneRates == null)
                return 0;

            $base = $zoneRates['basic'];
            $additional = $zoneRates['additional'];
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if (eval("return " .  $weight . $value['condintion'] . $value['weight'] . ";"))
                        $additional = $additional - ($additional * $value['percent']);
                }
            }

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

    public function webhook($shipmentInfo, $data)
    {
        $this->loadProvider($shipmentInfo['carrier_name'], $shipmentInfo['merchant_id']);

        $chargeableWeight = $this->adapter->trackShipment([$shipmentInfo['external_awb']], true)['ChargeableWeight'] ?? null;
        if ($chargeableWeight == null)
            throw new CarriersException('Chargeable Weight Is Zero');

        if ($chargeableWeight)
            $setup['chargable_weight'] = $this->calculateFees(
                $shipmentInfo['carrier_id'],
                null,
                ($shipmentInfo['group'] == 'DOM') ? $shipmentInfo['consignee_city'] : $shipmentInfo['consignee_country'],
                $shipmentInfo['group'],
                $chargeableWeight
            );

        $updated = $this->adapter->setup[$data['UpdateCode']] ?? ['status' => 'PROCESSING'];

        $actions = $updated['actions'] ?? [];
        if (isset($updated['actions']))
            unset($updated['actions']);

        foreach ($actions as $action) {
            if ($action == 'create_transaction')
                Transaction::create(
                    [
                        'type' => 'CASHIN',
                        'merchant_id' => $shipmentInfo['merchant_id'],
                        'source' => 'SHIPMENT',
                        'status' => 'COMPLETED',
                        'created_by' => $shipmentInfo['created_by'],
                        'balance_after' => ($shipmentInfo['cod'] - $shipmentInfo['fees']) + $this->merchantInfo->actual_balance,
                        'amount' => ($shipmentInfo['cod'] - $shipmentInfo['fees']),
                        'resource' => 'API'
                    ]
                );
            else if ($action == 'update_merchant_balance') {
                $this->merchantInfo->actual_balance =  ($shipmentInfo['cod'] - $shipmentInfo['fees']) + $this->merchantInfo->actual_balance;
                $this->merchantInfo->save();
            }
        }
        $logs = collect($shipmentInfo->logs);

        $updated['logs'] = $logs->merge([[
            'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'UpdateLocation' => $data['Comment1'],
            'UpdateDescription' => $updated['status']
        ]]);
        
        $shipmentInfo->update($updated);
        return true;
    }
}
