<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Messenger\SmsService;

use App\Http\Repositories\User\IUserRepo;
class OtpController extends Controller
{
    protected $userRepo;
    public function __construct(
        IUserRepo $user
    )
    {
        $this->userRepo = $user;
    }

    public function checkOTP(Request $request)
    {      
        $msg = "Phone verified";
        $code = 200;
        if($request->user()->is_phone_verified == 0) {
            $msg = "Phone not verified";
            $code = 500;
        }

        return $this->response(['msg' => $msg],$code);
    }

    public function sendVerification(Request $request)
    {
        SmsService::sendPinCode($request->user()->phone);
        return $this->response(['msg' => 'check your pin code'],200);
    }

    public function verifyPhoneNumber(Request $request)
    {

        $this->validate(request(), [
            'pin_code' => 'required',
        ]);

        $msg = "Invalid PinCode";
        $code = 500;

        if(auth()->user()->pin_code == $request->pin_code) {
            $this->userRepo->update(['is_phone_verified' => true,'phone_verified_at' => now(),'pin_code' => null] , ['id' => Request()->user()->id]);
            $msg = "Phone verified";
            $code = 200;
        }
        return $this->response(['msg' => $msg],$code);

        
    }
    
}
