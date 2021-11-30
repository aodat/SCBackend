<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['provider'];

    protected $casts = [
        'logs' => 'array'
    ];

    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }

        return parent::castAttribute($key, $value);
    }

    public function getProviderAttribute()
    {
        return 'aramex'; // $this->hasOne(Carriers::class,'id','carrier_id');
    }
}