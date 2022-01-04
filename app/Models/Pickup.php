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
    protected $casts = [
        'address_info' => 'array'
    ];

    public function getCarrierNameAttribute()
    {
        $provider = App::make('carriers')->where('id', $this->carrier_id)->first();
        return $provider->name ?? '';
    }

    public function getAddressNameAttribute()
    {
        return $this->address_info['name'];
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->where('merchant_id', Request()->user()->merchant_id)->orderBy('created_at', 'desc');
        });
    }
}
