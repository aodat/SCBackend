<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;
    protected $guarded = [];


    protected $hidden = [
        'is_email_verified' ,'is_phone_verified','is_documents_verified','is_active','is_instant_payment_active'
    ];

    public function user()
    {
        return $this->hasMany(User::class,'merchant_id','id');
    }
}
