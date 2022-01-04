<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class Merchant extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'payment_methods' => 'array',
        'documents' => 'array',
        'addresses' => 'array',
        'senders' => 'array',
        'domestic_rates' => 'array',
        'express_rates' => 'array',
        'carriers' => 'array',
        'rules' => 'array'
    ];

    protected $hidden = [
        'is_documents_verified', 'is_active', 'is_instant_payment_active',
        'domestic_rates',
        'express_rates'
    ];

    protected $appends  = ['config'];
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $path = Request()->path();
        if (strpos($path, 'admin/merchant/lists'))
            array_push($this->hidden, 'payment_methods', 'documents', 'addresses', 'senders', 'domestic_rates', 'express_rates');
        else if (strpos($path, 'merchant/info')) {
            array_push($this->appends, 'domastic', 'express');
            array_push($this->hidden, 'payment_methods', 'documents', 'addresses');
        }
    }

    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }

        return parent::castAttribute($key, $value);
    }

    public function getDomasticAttribute()
    {
        return $this->domastic();
    }

    public function getExpressAttribute()
    {
        return $this->express();
    }

    public function user()
    {
        return $this->hasMany(User::class, 'merchant_id', 'id');
    }

    public static function getAdressInfoByID($id)
    {
        $addresses = DB::table('merchants')
            ->where('id', '=', Request()->user()->merchant_id)
            ->select('addresses')
            ->first()
            ->addresses;
        return collect(json_decode($addresses))->where('id', '=', $id)->first();
    }


    protected function domastic()
    {
        $enabledCarriers = Carriers::get()->where('is_enabled', 1);
        if ($enabledCarriers == null)
            return [];
        $enabledCarriers = $enabledCarriers->pluck('name', 'id');
        $domestic_rates = App::make('merchantInfo')->domestic_rates;
        $domestic_rates = collect($domestic_rates)->reject(function ($value, $key) use ($enabledCarriers) {
            return !(isset($enabledCarriers[$key]));
        })->keyBy(function ($value, $key) use ($enabledCarriers) {
            return $enabledCarriers[$key];
        });
        return $domestic_rates;
    }

    protected function express()
    {
        $enabledCarriers = Carriers::get()->where('is_enabled', 1);
        if ($enabledCarriers == null)
            return [];
        $enabledCarriers = $enabledCarriers->pluck('name', 'id');
        $express_rates = App::make('merchantInfo')->express_rates;
        $express_rates = collect($express_rates)->reject(function ($value, $key) use ($enabledCarriers) {
            return !(isset($enabledCarriers[$key]));
        })->keyBy(function ($value, $key) use ($enabledCarriers) {
            return $enabledCarriers[$key];
        });
        return $express_rates;
    }

    public function getConfigAttribute()
    {
        return [
            'countries' => collect(json_decode(Storage::disk('local')->get('template/countries.json'), true)),
            'payment_providers' =>  collect(json_decode(Storage::disk('local')->get('template/payment_providers.json'), true))[$this->country_code] ?? []
        ];
    }
}
