<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $appends = ['consignee_name'];
    protected $casts = [
        'payment_method' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d');
    }

    public function getConsigneeNameAttribute()
    {
        return DB::table('shipments')->where('merchant_id', $this->merchant_id)->where('awb', $this->item_id)->first()->consignee_name ?? null;
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
