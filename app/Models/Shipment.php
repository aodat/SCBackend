<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Shipment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = [ 'generator_name'];

    protected $casts = [
        'logs' => 'array',
        'is_doc' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function getCarrierNameAttribute()
    {     
        return Carriers::find($this->carrier_id)->name;
    }

    public function getGeneratorNameAttribute()
    {
        return User::find($this->created_by)->name;
    }

    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }
        return parent::castAttribute($key, $value);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getLogsAttribute($logs)
    {
        return collect(json_decode($logs))->sortByDesc('UpdateDateTime')->flatten();
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            if (Request()->user() !== null && Request()->user()->role != 'super_admin') {
                $builder->where('merchant_id', Request()->user()->merchant_id)->where('is_deleted', false)->orderBy('created_at', 'desc');
            } else {
                $builder->orderBy('created_at', 'desc');
            }
        });

    }
}
