<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\SmsService;
use App\Http\Requests\Merchant\MerchantRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\Pincode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MerchantController extends Controller
{
    // Get Merchant Info
    public function merchantProfile(MerchantRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        return $this->response($merchant, 'Data Retrieved Successfully');
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
        return $this->successful('Updated Successfully');
    }

    // User profile info
    public function profile(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        return $this->response($user, 'Data Retrieved Successfully');
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

        return $this->successful('Updated Successfully');
    }

    // Update User Profile
    public function updatePassword(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        if (Hash::check($request->current, $user->password) == false) {
            return $this->error('Current Password Is Wrong', 400);
        }

        $user->password = Hash::make($request->new);
        $user->save();

        return $this->successful('Updated Successfully');
    }

    public function verifyPhone(MerchantRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        $merchant->phone_verified_at = Carbon::now();
        $merchant->save();

        return $this->successful('Merchant Phone Verified');
    }

    public function pincode(MerchantRequest $request)
    {
        $pincode = Pincode::orderBy('id', 'desc')->first();
        if (
            $pincode == null ||
            isset($pincode->created_at) && $pincode->created_at->diffInSeconds() > 300
        ) {
            if ($pincode) {
                $pincode->status = 'inactive';
                $pincode->save();
            }
            $random = mt_rand(111111, 999999);
            SmsService::sendSMS($random, App::make('merchantInfo')->phone);
            PinCode::create([
                "code" => $random,
                'Merchant_id' => App::make('merchantInfo')->id,
                "status" => 'active',
            ]);
        } else {
            throw new InternalException('Pincode Code Was Sent before 5 Min');
        }

        return $this->successful('Pincode Code Was Sent');
    }

    public function getCountries()
    {
        return $this->response(Country::all(), "Data Retrieved Successfully");
    }

    public function getCities($code)
    {
        return $this->response(Country::getCities($code), "Data Retrieved Successfully");
    }

    public function getAreas($code)
    {
        return $this->response(City::getAreas($code), "Data Retrieved Successfully");
    }

    public function getMerchentInfo()
    {
        if (Request()->user() !== null) {
            return Merchant::findOrFail(Request()->user()->merchant_id);
        } else {
            return Merchant::findOrFail(1);
            // Guest Account
        }
    }
}
