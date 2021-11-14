<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
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
use App\Models\Transaction;

class MerchantController extends Controller
{
    public function profile(MerchantRequest $request)
    {
        $data = User::whereHas('merchant', function ($query) {
            return $query->where('users.id', '=', Auth::id());
        })->get();

        $data = User::with('merchant')->where('users.id', '=', Auth::id())->get();
        return $this->response($data, 'User Profile Information', 200);
    }

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

    public function dashboardInfo(MerchantRequest $request)
    {
        $merchant_id =  $request->user()->merchant_id;

        if ($request->since_at !== null && $request->until !== null) {


            $since_at = date("Y-m-d H:i:s", strtotime($request->input('date') . " " . $request->since_at));
            $until = date("Y-m-d H:i:s", strtotime($request->input('date') . " " .  $request->until));
            if ($since_at  >= $until)
                throw new InternalException("Error since_at Date End until ");
        } else {
            $since_at = Carbon::now()->subDays(7);
            $until = Carbon::now();
        }

        $data['shipment']['defts'] =   DB::table('shipments as shp')
            ->join('transactions as t', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.status', '=', "DRAFT")
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select(DB::raw("sum(t.amount) as amount"))->first();
        $data['shipment']['defts'] = $data['shipment']['defts']->amount ?? 0;

        $data['shipment']['proccesing'] =   DB::table('shipments as shp')
            ->join('transactions as t', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.status', '=', "PROCESSING")
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select(DB::raw("sum(t.amount) as amount"))->first();
        $data['shipment']['proccesing'] = $data['shipment']['proccesing']->amount ?? 0;

        $data['shipment']['delivered']  = DB::table('shipments as shp')
            ->join('transactions as t', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.status', '=', "COMPLETED")
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select(DB::raw("sum(t.amount) as amount"))->first();
        $data['shipment']['delivered'] = $data['shipment']['delivered']->amount ?? 0;

        $data['payment']['income'] = Transaction::where('merchant_id',  $merchant_id)
            ->where("type", "=", "CASHOUT")
            ->whereBetween('created_at', [$since_at, $until])
            ->select(DB::raw("sum(amount) as amount"))
            ->first();
        $data['payment']['income'] = $data['payment']['income']->amount ?? 0;

        $data['payment']['Outcome'] = Transaction::where('merchant_id', $merchant_id)
            ->where("type", "=", "CASHIN")
            ->whereBetween('created_at', [$since_at, $until])
            ->select(DB::raw("sum(amount) as amount"))
            ->first();
        $data['payment']['Outcome'] = $data['payment']['Outcome']->amount ?? 0;

        $data['payment']['pending_payment'] = Transaction::where('merchant_id',  $merchant_id)
            ->where("type", "=", "CASHIN")
            ->whereBetween('created_at', [$since_at, $until])
            ->select(DB::raw("sum(amount) as amount"))
            ->first();
        $data['payment']['pending_payment'] = $data['payment']['pending_payment']->amount ?? 0;
        return $data;
    }
    public function getMerchentInfo($id = null)
    {
        if ($id == null)
            $id = Request()->user()->merchant_id;
        return Merchant::findOrFail($id);
    }
}
