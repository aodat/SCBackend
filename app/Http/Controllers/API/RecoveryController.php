<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecoveryRequest;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

use App\Http\Repositories\User\IUserRepo;
class RecoveryController extends Controller
{

    protected $userRepo;
    public function __construct(
        IUserRepo $user
    )
    {
        $this->userRepo = $user;
    }

    public function forgetpassword(RecoveryRequest $request)
    {
        $response =  Password::sendResetLink($request->only('email'));
        if ($response == Password::RESET_LINK_SENT) {
            $msg = "Mail send successfully";
            $code = 400;

        } else {
            $msg = "Email could not be sent to this email address";
            $code = 400;

        }
        $this->response(['msg' => $msg],$code);
    }


    protected function sendResetResponse(RecoveryRequest $request)
    {
        $response = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($response == Password::PASSWORD_RESET) {
            $msg = "Password reset successfully";
            $code = 200;
        } else {
            $msg = "Email could not be sent to this email address";
            $code = 400;
        }
        $this->response(['msg' => $msg],$code);
    }

    public function verify($userID, Request $request) {
        if (!$request->hasValidSignature()) {
            return $this->response(['msg' => 'Invalid/Expired url provided.'],401);
        }
    
        $user = $this->userRepo->getUseriInfo($userID);
    
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    
        return $this->response(['msg' => 'Email verified sucessfully'],200);
    }
    
    public function resend() {
        if (auth()->user()->hasVerifiedEmail()) {
            return $this->response(['msg' => 'Email already verified.'],401);
        }
    
        auth()->user()->sendEmailVerificationNotification();
    
        return $this->response(['msg' => 'Email verification link sent on your email id.'],200);
    }
    
}
