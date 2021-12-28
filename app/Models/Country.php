<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Country extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'rates' => 'array',
        'zipcode' => 'boolean'
    ];

    protected function castAttribute($key, $value)
    {
        if ($this->getCastType($key) == 'array' && is_null($value)) {
            return [];
        }

        return parent::castAttribute($key, $value);
    }

    public static function getCities($id)
    {
        return DB::table('countries as c')->join('cities as ci', 'c.id', 'ci.country_id')
            ->where('c.id', $id)
            ->orWhere('c.code', strtoupper($id))
            ->select('ci.*')
            ->get();
    }
}
