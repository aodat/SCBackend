<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\App;

class Carriers extends Model
{
    use HasFactory;
    protected $merchantCarriers;
    protected $guarded = [];


    protected $casts = [
        'express' => 'boolean',
        'domestic' => 'boolean',
        'accept_cod' => 'boolean'
    ];

    protected $appends = [
        'is_enabled', 'is_defult', 'carrier_id'
    ];

    protected $hidden = [
        'email', 'phone', 'balance', 'country_code', 'currency_code', 'documents','updated_at','created_at',
        'is_email_verified', 'is_phone_verified', 'is_documents_verified', 'is_active', 'id'
    ];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->merchantCarriers = App::make('merchantCarriers');
    }

    public function getCarrierIdAttribute()
    {
        return $this->id;
    }

    public function getIsDefultAttribute()
    {
        $list = $this->merchantCarriers->where('carrier_id', $this->id)->first();
        if ($list == null)
            return false;
        return ($list['is_defult']);
    }

    public function getIsEnabledAttribute()
    {
        $list = $this->merchantCarriers->where('carrier_id', $this->id)->first();
        if ($list == null)
            return true;

        return ($list['is_enabled']);
    }
}
