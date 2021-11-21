<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Http\Requests\Merchant\MerchantRequest;

use App\Http\Controllers\Utilities\SmsService;

use App\Models\Merchant;
use App\Models\User;

class MerchantController extends Controller
{
    // Get Merchant Info
    public function merchantProfile(MerchantRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        return $this->response($merchant, 'Merchant Information', 200);
    }

    // Update Merchant Profile
    public function updateMerchantProfile(MerchantRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        $merchant->type = $request->type;
        $merchant->name = $request->name;
        $merchant->phone = $request->phone;
        $merchant->email = $request->email;
        $merchant->save();

        return $this->successful('Updated Sucessfully');
    }

    // User profile info
    public function profile(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        return $this->response($user, 'User Profile Information', 200);
    }

    // Update User Profile
    public function updateProfile(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        $user->email = $request->email;

        if ($user->isDirty('email')) {
            $user->is_email_verified = false;
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }

        $user->name = $request->name;

        $user->phone = $request->phone;
        if ($user->isDirty('phone')) {
            $user->is_phone_verified = false;
            $user->phone_verified_at = null;
        }
        $user->save();

        return $this->successful('Profile Updated Successfully');
    }

    // Update User Profile
    public function updatePassword(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        if (Hash::check($request->current, $user->password) == false)
            return $this->error('Current Password Is Wrong', 500);

        $user->password = Hash::make($request->new);
        $user->save();

        return $this->successful('Password Updated Successfully');
    }

    public function verifyPhoneNumber(MerchantRequest $request)
    {
        $randomPinCode = mt_rand(111111, 999999);

        SmsService::sendSMS($request->phone, $randomPinCode);

        $merchantID = $request->user()->merchant_id;
        Merchant::where('id', $merchantID)->update(['pin_code' => $randomPinCode]);
        return $this->successful('Check Your Mobile');
    }

    public function getMerchentInfo($id = null)
    {
        if ($id == null)
            $id = Request()->user()->merchant_id;
        return Merchant::findOrFail($id);
    }
}
