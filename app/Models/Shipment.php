<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Shipment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['provider', 'carrier_name'];
    protected $casts = [
        'logs' => 'array'
    ];

    public function getCarrierNameAttribute()
    {
        $provider = App::make('carriers')->where('id', $this->carrier_id)->first();
        return $provider->name ?? '';
    }
    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }

        return parent::castAttribute($key, $value);
    }

    public function getProviderAttribute()
    {
        $provider = App::make('carriers')->where('id', $this->carrier_id)->first();
        return $provider->name ?? '';
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->where('merchant_id', Request()->user()->merchant_id);
        });
    }
}
