<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $hidden = [
        'notes'
    ];

    protected $appends = [
        'shipment_info'
    ];

    protected $casts = [
        'payment_method' => 'array'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getShipmentInfoAttribute()
    {
        if ($this->subtype == 'COD' && !is_null($this->item_id)) {
            $shipment = DB::table('shipments')->where('merchant_id', $this->merchant_id)->where('awb', $this->item_id)->first();
            if (is_null($shipment))
                return [];
            return [
                'sender_name' => $shipment->sender_name,
                'consignee_name' => $shipment->consignee_name,
                'consignee_phone' => $shipment->consignee_phone,
                'consignee_city' => $shipment->consignee_city,
                'consignee_address_description' => $shipment->consignee_address_description,
                'chargable_weight' => $shipment->chargable_weight,
                'cod' => $shipment->cod,
                'fees' => $shipment->fees,
                'net' => $shipment->cod - $shipment->fees,
            ];
        } else
            return [];
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            if (Request()->user() !== null && Request()->user()->role != 'super_admin') {
                $builder->where('merchant_id', Request()->user()->merchant_id)->orderBy('created_at', 'desc');
            } else {
                $builder->orderBy('created_at', 'desc');
            }
        });
    }
}
