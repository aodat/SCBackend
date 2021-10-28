<?php
 
namespace App\Models;
 
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Notifications\MailResetPasswordNotification as MailResetPasswordNotification;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;
 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id' , 'name', 'email', 'password','phone', 'pin_code',
        'is_email_verified' ,'is_phone_verified','email_verified_at','phone_verified_at','merchant_id'
    ];
 
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password' , 'remember_token' , 'pin_code',
        'is_email_verified' ,'is_phone_verified','email_verified_at','phone_verified_at'
    ];
 
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MailResetPasswordNotification($token));
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class,'id','merchant_id');
    }
}