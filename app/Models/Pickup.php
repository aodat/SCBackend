<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Pickup extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['carrier_name', 'address_name'];

    public static function getPickupCarrires($merchant_id, $pickup_id = null, $carrier_id = null, $all = false)
    {
        $sql = DB::table('pickups as ups')
            ->join('carriers as c', 'ups.carrier_id', 'c.id')
            ->where('merchant_id', '=', $merchant_id)
            ->select(DB::raw('ups.*,c.name'));

        if ($pickup_id)
            $sql->where('ups.id', '=', $pickup_id);

        if ($carrier_id)
            $sql->where('c.id', '=', $carrier_id);

        if (!$all)
            return $sql->first();
        return $sql->get();
    }

    public function getCarrierNameAttribute()
    {
        $provider = App::make('carriers')->where('id', $this->carrier_id)->first();
        return $provider->name ?? '';
    }

    public function getAddressNameAttribute()
    {
        $address = App::make('merchantAddresses')->where('id', $this->address_id)->first();
        return $address['name'] ?? '';
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->where('merchant_id', Request()->user()->merchant_id);
        });
    }
}
