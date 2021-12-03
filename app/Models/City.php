<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class City extends Model
{
    use HasFactory;
    protected $guarded = [];

    public static function getAreas($id)
    {
        return DB::table('cities as c')->join('areas as ar', 'c.id', 'ar.city_id')
            ->where('c.id', $id)
            ->orWhere('c.code', strtoupper($id))
            ->select('ar.*')
            ->get();
    }
}
