<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

class Pickup extends Model
{
    use HasFactory;
    protected $guarded = [];

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
}
