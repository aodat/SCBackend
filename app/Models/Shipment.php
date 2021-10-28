<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['provider'];
    public function getProviderAttribute() {
        return 'aramex';// $this->hasOne(Carriers::class,'id','carrier_id');

    }
}