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
        $sql =  DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select('shp.status', DB::raw('sum(amount) as amount'))
            ->groupBy('shp.status')
            ->get();

        $shiping = collect($sql)->pluck('amount', 'status');
        $sql2 = Transaction::where('merchant_id',  $merchant_id)
            ->whereBetween('created_at', [$since_at, $until])
            ->select('type', DB::raw('sum(amount) as amount'))
            ->groupBy('type')
            ->get();


        $payment = collect($sql2)->pluck('amount', 'type');

        $pending_payment =   DB::table('transactions as t')
            ->join('shipments as shp', 'shp.id', 't.item_id')
            ->where('shp.merchant_id', '=',  $merchant_id)
            ->where('shp.transaction_id', '=',  null)
            ->whereBetween('t.created_at', [$since_at, $until])
            ->select( DB::raw('sum(amount) as amount'))
            ->first();
            $pending_payment = collect($pending_payment);
        $data = [
            "shiping" => [
                "defts" => $shiping['DRAFT'] ?? 0,
                "proccesing" => $shiping['PROCESSING'] ?? 0,
                "delivered" => $shiping['COMPLETED'] ?? 0,
                "renturnd" => $shiping['RENTURND'] ?? 0,
            ],

            "payment" => [
                "Outcome" => $payment['CASHOUT'] ?? 0,
                "income" => $payment['CASHIN'] ?? 0,
                "pending_payment" =>$pending_payment['amount'] ?? 0,

            ]
        ];
        return $data;
    }
    public function getMerchentInfo($id = null)
    {
        if ($id == null)
            $id = Request()->user()->merchant_id;
        return Merchant::findOrFail($id);
    }
}
