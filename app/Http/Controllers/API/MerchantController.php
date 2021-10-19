<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;

use App\Http\Requests\MerchantRequest;
use App\Models\Merchant;
use App\Models\User;

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

        return $this->response(['msg' => 'Profile Updated Sucessfully'],200);
    }

    public function updatePassword(MerchantRequest $request)
    {

        $user = User::findOrFail(Auth::id());
        if (Hash::check($request->current,$user->password) == false)
            return $this->response(['msg' => 'Current Password Is Wrong'],500);

        $user->password = Hash::make($request->new);
        $user->save();

        return $this->response(['msg' => 'Password Updated Sucessfully'],200);
    }

    public function getPaymentMethods(MerchantRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('payment_methods')->first();

      
        if(collect($data->payment_methods)->isEmpty())
            return $this->notFound();

        return $this->response(['msg' => 'All Payment Methods','data' => $data->payment_methods],200);
    }

    public function createPaymentMethod(MerchantRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $json = $request->json()->all();
        
        $merchant = Merchant::where('id',$merchantID);

        $paymentMethods = collect($merchant->select('payment_methods')->first()->payment_methods);
        $counter = $paymentMethods->max('id') ?? 0;
        $json['id'] = ++$counter;

        /* 
        // for multi payment method
        $json = $data->map(function ($value) use(&$counter) {
            if(!isset($value['id']))
                $value['id'] = ++$counter;
            return $value;
        });
        */

        $merchant->update(['payment_methods' => $paymentMethods->merge([$json])]);
        return $this->response(['msg' => 'Payment Created Sucessfully'],200);
    }

    public function deletePaymentMethod($paymentID,MerchantRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        
        $list = Merchant::where('id',$merchantID);
        $paymentMethods = collect($list->select('payment_methods')->first()->payment_methods);

        $json = $paymentMethods->reject(function ($value) use($paymentID) {
            if($value['id'] == $paymentID)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['payment_methods' => collect($json)]);
        return $this->response(null,204);
    }
}
