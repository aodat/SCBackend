<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Http\Requests\Merchant\MerchantRequest;
use App\Models\Merchant;
use App\Models\User;

use App\Http\Controllers\Utilities\SmsService;
class MerchantController extends Controller
{
    public function profile(MerchantRequest $request)
    {
        $data = User::whereHas('merchant', function ($query) {
            return $query->where('users.id', '=', Auth::id());
        })->get();

        $data = User::with('merchant')->where('users.id','=',Auth::id())->get();
        return $this->response(['msg' => 'User Profile Info','data' => $data],200);
    }

    public function updateProfile(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        $user->email = $request->email;
        
        if($user->isDirty('email'))
        {
            $user->is_email_verified = false;
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }
        
        $user->name = $request->name;
        
        $user->phone = $request->phone;
        if($user->isDirty('phone'))
        {
            $user->is_phone_verified = false;
            $user->phone_verified_at = null;
        }
        $user->save();

        return $this->successful(null,204);
    }

    public function updatePassword(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        if (Hash::check($request->current,$user->password) == false)
            return $this->error(['msg' => 'Current Password Is Wrong'],500);

        $user->password = Hash::make($request->new);
        $user->save();

        return $this->successful(null,204);
    }

    public function verifyPhoneNumber(MerchantRequest $request)
    {
        $randomPinCode = mt_rand(111111, 999999);

        SmsService::sendSMS($request->phone,$randomPinCode);

        $merchantID = $request->user()->merchant_id;
        Merchant::where('id',$merchantID)->update(['pin_code' => $randomPinCode]);
        return $this->successful(null,204);
    }

    public function getMerchentInfo($id)
    {
        return Merchant::findOrFail($id);
    }
}
