<?php

namespace App\Models;

use App\Jobs\UserEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
        'is_email_verified', 'is_phone_verified', 'email_verified_at', 'phone_verified_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_owner' => 'boolean',
    ];

    public function sendPasswordResetNotification($token)
    {
        UserEmail::dispatch($this, 'reset_password', $token);
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'id', 'merchant_id');
    }
}
