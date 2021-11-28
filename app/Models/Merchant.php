<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

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

    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }

        return parent::castAttribute($key, $value);
    }
    protected $hidden = [
        'is_email_verified', 'is_phone_verified',
        'is_documents_verified', 'is_active', 'is_instant_payment_active'
    ];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $path = Request()->path();
        if (strpos($path, 'admin/merchant/lists'))
            array_push($this->hidden, 'payment_methods', 'documents', 'addresses', 'senders', 'domestic_rates', 'express_rates');
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
}
