<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Http\Requests\Merchant\MerchantRequest;
use App\Models\Merchant;
use App\Models\User;

use App\Http\Controllers\Utilities\SmsService;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MerchantController extends Controller
{
    public function profile(MerchantRequest $request)
    {
        $data = User::whereHas('merchant', function ($query) {
            return $query->where('users.id', '=', Auth::id());
        })->get();

        $data = User::with('merchant')->where('users.id','=',Auth::id())->get();
        return $this->response($data,'User Profile Information',200);
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

        return $this->successful('Profile Updated Successfully');
    }

    public function updatePassword(MerchantRequest $request)
    {
        $user = User::findOrFail(Auth::id());
        if (Hash::check($request->current,$user->password) == false)
            return $this->error('Current Password Is Wrong',500);

        $user->password = Hash::make($request->new);
        $user->save();

        return $this->successful('Password Updated Successfully');
    }

    public function verifyPhoneNumber(MerchantRequest $request)
    {
        $randomPinCode = mt_rand(111111, 999999);

        SmsService::sendSMS($request->phone,$randomPinCode);

        $merchantID = $request->user()->merchant_id;
        Merchant::where('id',$merchantID)->update(['pin_code' => $randomPinCode]);
        return $this->successful('Check Your Mobile');
    }

    public function dashboardInfo(MerchantRequest $request)
    {
        dd($request->all());

        $shipmentInfo = Shipment::where('merchant_id',$request->user()->merchant_id);
        
        $result['overall']['PROCESSING'] = 0;
        $result['overall']['DRAFT'] = 0;
        $result['overall']['COMPLETED'] = 0;

        $result['today']['PROCESSING'] = 0;
        $result['today']['DRAFT'] = 0;
        $result['today']['COMPLETED'] = 0;

        $result['today'] =  $shipmentInfo->select('status',DB::raw('count(status) as counter'))
                                ->whereDate('created_at', '=', Carbon::today()->toDateString())
                                ->groupBy('status')
                                ->pluck('counter','status');
        $result['overall'] = $shipmentInfo->select('status',DB::raw('count(status) as counter'))
                                ->groupBy('status')
                                ->pluck('counter','status');
        $result['onHold'] = [];

        $result['rates']['delivered'] = Shipment::where('merchant_id',$request->user()->merchant_id)
                                        ->select(DB::raw('count(delivered_at) as counter'))
                                        ->whereNotNull('delivered_at')
                                        ->first()->counter;
        $result['rates']['returned'] = (
                                            $result['overall']['PROCESSING'] +
                                            $result['overall']['DRAFT'] +
                                            $result['overall']['COMPLETED']
                                        ) - $result['overall']['DRAFT'];

        $merchantInfo = $this->getMerchentInfo();
        $result['balances']['available'] = $merchantInfo->actual_balance;
        $result['balances']['actual'] = $merchantInfo->available_balance;
        return $result;
    }

    public function getMerchentInfo($id = null)
    {
        if($id == null)
            $id = Request()->user()->merchant_id;
        return Merchant::findOrFail($id);
    }
}
