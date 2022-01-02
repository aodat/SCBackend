<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Shipment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['carrier_name', 'generator_name'];

    protected $casts = [
        'logs' => 'array',
        'is_doc' => 'boolean'
    ];

    public function getCarrierNameAttribute()
    {
        $provider = App::make('carriers')->where('id', $this->carrier_id)->first();
        return $provider->name ?? '';
    }

    public function getGeneratorNameAttribute()
    {
        return User::findOrFail($this->created_by)->name;
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

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            if (Request()->user() !== null)
                $builder->where('merchant_id', Request()->user()->merchant_id)->orderBy('created_at', 'desc');
            else
                $builder->orderBy('created_at', 'desc');
        });
    }
}
