<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoices extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected static function booted()
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            if (Request()->user() !== null && Request()->user()->role != 'super_admin') {
                $builder->where('merchant_id', Request()->user()->merchant_id)->orderBy('created_at', 'desc');
            } else {
                $builder->orderBy('created_at', 'desc');
            }
        });

    }
}
