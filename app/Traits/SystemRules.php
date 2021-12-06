<?php

namespace App\Traits;

use App\Exceptions\InternalException;
use Illuminate\Support\Facades\App;

trait SystemRules
{
    public function getActionShipments($shipmentInfo)
    {
        $merchantRules = collect(App::make('merchantRules'));
        $rules = $merchantRules->pluck('rules', 'id');
        $actions = $rules->reject(function ($rule) use ($shipmentInfo) {
            foreach ($rule as $key => $value) {
                if ($value['sub-type'] == 'weight')
                    return !eval('return ' . $value['value'] . ' ' . $value['constraint'] . ' ' . $shipmentInfo['actual_weight'] . ';');
                else if ($value['sub-type'] == 'cod')
                    return true;
                else if ($value['type'] == $shipmentInfo['type']) {
                    if ($value['type'] == 'cod')
                        return ($value[0] == $shipmentInfo['consignee_city']);
                    else
                        return ($value[0] == $shipmentInfo['consignee_city'] && $value[1] == $shipmentInfo['consignee_country']);
                }
            }
        });

        if ($actions->isEmpty())
            throw new InternalException('No rules applied', 400);


        $provider = $merchantRules->whereIn('id', $actions->keys())->pluck('action', 'id')->first()['0']['create_shipment'];
        return $provider;
    }
}