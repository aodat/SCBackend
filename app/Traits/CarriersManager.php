<?php

namespace App\Traits;

use App\Exceptions\CarriersException;
use App\Http\Controllers\Utilities\InvoiceService;
use App\Models\Carriers;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Libs\Aramex;
use Libs\DHL;
use Libs\Fedex;

trait CarriersManager
{
    public $adapter;
    private $merchantInfo;
    public function loadProvider($provider, $settings = array())
    {
        $provider = strtoupper($provider);
        if (empty($settings)) {
            $settings = Carriers::where('id', 3)->first()->env ?? null;
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

        $this->merchantInfo = $this->getMerchantInfo();
    }

    public function getMerchantInfo()
    {
        if (Request()->user() === null) {
            return Merchant::findOrFail(900);
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

    public function generatePickup($provider, $pickup_date, $address)
    {
        $this->loadProvider($provider);
        return $this->adapter->createPickup($this->merchantInfo->email, $pickup_date, $address);
    }

    public function printShipment($shipments_number)
    {
        $shipments = DB::table('shipments')
            ->whereIn('external_awb', $shipments_number)
            ->get();

        $exported = [];
        $shipments->map(function ($shipment) use (&$exported) {
            $exported[] = $shipment->url;
            if ($shipment->group == 'EXP' && !$shipment->is_doc) {
                $exported[] = InvoiceService::commercial($shipment);
            }

        });

        return mergePDF($exported);
    }

    public function cancelPickup($provider, $pickupInfo)
    {
        $this->loadProvider($provider);
        return $this->adapter->cancelPickup($pickupInfo);
    }

    public function track($provider, $shipments_number, $all = false)
    {
        $this->loadProvider($provider);
        return $this->adapter->trackShipment($shipments_number, $all);
    }

    /*
    $type : DOM , Express
     */
    public function calculateFees($carrier_id, $from = null, $to, $type, $weight)
    {
        $this->merchantInfo = $this->getMerchantInfo();
        if ($type == 'domestic' || $type == 'DOM') {
            if (!isset($this->merchantInfo['domestic_rates'][$carrier_id])) {
                throw new CarriersException('The Carrier ID ' . $carrier_id . ' No Support domestic , Please Contact Administrators');
            }

            $rate = collect($this->merchantInfo['domestic_rates'][$carrier_id])->where('code', $to);

            if ($rate->isEmpty()) {
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');
            }

            $price = $rate->first()['price'];
            $fees = ceil($weight / 10) * $price;
        } else {
            $express_rates = collect(Country::where('code', $this->merchantInfo['country_code'])->first());
            if ($express_rates->isEmpty()) {
                throw new CarriersException('Country Code Not Exists, Please Contact Administrators');
            }

            $express_rates = $express_rates['rates'];
            if (count($express_rates) == 0) {
                throw new CarriersException('No Setup Added To This Country, Please Contact Administrators');
            }

            if (!isset($express_rates[$to])) {
                throw new CarriersException('No Setup Added To This Country, Please Contact Administrators');
            }

            $rates = collect($express_rates[$to]);
            $zones = $rates->where('carrier_id', $carrier_id);

            if ($zones->count() > 1) {
                throw new CarriersException('Somthing Wrong On Rates Setup, Please Contact Administrators');
            }

            if (!isset($zones->first()['zone_id'])) {
                throw new CarriersException('Somthing Wrong On Zone ID Setup, Please Contact Administrators');
            }

            $zone_id = $zones->first()['zone_id'];
            $discounts = $this->merchantInfo['express_rates'][$carrier_id]['discounts'] ?? [];

            $zoneRates = collect($this->merchantInfo['express_rates'][$carrier_id]['zones'])->where('id', $zone_id);
            if ($zoneRates->count() > 1) {
                throw new CarriersException('Express Rates Json Retrun More Than One Zone In User Merchant ID');
            }

            $zoneRates = $zoneRates->first();
            if ($zoneRates == null) {
                return 0;
            }

            $base = $zoneRates['basic'];
            $additional = $zoneRates['additional'];
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if (eval("return " . $weight . $value['condintion'] . $value['weight'] . ";")) {
                        $additional = $additional - ($additional * $value['percent']);
                    }

                }
            }

            $fees = 0;
            if ($weight > 0) {
                $weights_count = ceil($weight / 0.5);
                $weight_fees = (($weights_count - 1) * $additional) + $base;
                $fees += $weight_fees;
            }
        }

        if ($fees == 0) {
            throw new CarriersException('Fees Equal Zero');
        }

        return $fees;
    }

    public function webhook($shipmentInfo, $data)
    {
        $merchant = Merchant::findOrFail($shipmentInfo['merchant_id']);

        $details = $this->track($shipmentInfo['carrier_name'], $shipmentInfo['external_awb']) ?? null;

        if (!isset($details['ChargeableWeight'])) {
            throw new CarriersException('Chargeable Weight Is Zero');
        }

        $fees = $this->calculateFees(
            $shipmentInfo['carrier_id'],
            null,
            ($shipmentInfo['group'] == 'DOM') ? $shipmentInfo['consignee_city'] : $shipmentInfo['consignee_country'],
            $shipmentInfo['group'],
            $details['ChargeableWeight']
        );

        $updated = $this->adapter->setup[$data['UpdateCode']] ?? ['status' => 'PROCESSING'];

        $actions = $updated['actions'] ?? [];
        if (isset($updated['actions'])) {
            unset($updated['actions']);
        }

        foreach ($actions as $action) {
            if ($action == 'create_transaction') {
                $transaction = Transaction::create(
                    [
                        'type' => 'CASHIN',
                        'type' => 'COD',
                        'item_id' => $shipmentInfo['id'],
                        'merchant_id' => $shipmentInfo['merchant_id'],
                        'source' => 'SHIPMENT',
                        'status' => 'PROCESSING',
                        'created_by' => $shipmentInfo['created_by'],
                        'balance_after' => ($shipmentInfo['cod'] - $shipmentInfo['fees']) + $merchant->bundle_balance,
                        'amount' => ($shipmentInfo['cod'] - $shipmentInfo['fees']),
                        'resource' => 'API',
                    ]
                );
                $updated['transaction_id'] = $transaction->id;
            } else if ($action == 'update_merchant_balance') {
                if ($shipmentInfo['cod'] > 0) {
                    $merchant->cod_balance += $shipmentInfo['cod'];
                    $merchant->bundle_balance -= $fees;
                    $merchant->save();
                }
            }
        }

        $logs = collect($shipmentInfo->logs);

        $updated['chargable_weight'] = $details['ChargeableWeight'];
        $updated['logs'] = $logs->merge([[
            'UpdateDateTime' => Carbon::parse($data['UpdateDateTime'])->format('Y-m-d H:i:s'),
            'UpdateLocation' => $data['Comment1'],
            'Code' => $data['UpdateCode'] ?? 'N/A',
            'TrackingDescription' => $details['UpdateDescription'] ?? '',
            'UpdateDescription' => $updated['status'],
        ]]);

        $shipmentInfo->update($updated);
        return true;
    }
}
