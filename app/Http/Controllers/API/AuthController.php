<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\RecoveryRequest;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;


use App\Models\Merchant;
use App\Models\User;

class AuthController extends Controller
{
    public function login(AuthRequest $request)
    {
        if (!auth()->attempt(['email' => $request->email,'password' => $request->password])) {
            return $this->response(['msg' => 'Invalid Email or Password'],400);
        }
        $userData = auth()->user();
        return $this->response(['user' => $userData, 'access_token' => $userData->createToken('users')->accessToken],200);
    }

    public function register(AuthRequest $request)
    {
        $merchant = Merchant::create(
            [
                'name' => $request->name,
                'email' => $request->email,
                'type' => $request->type,
                'phone' => $request->phone
            ]
        );
        $user = User::create(
            [
                'merchant_id' => $merchant->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone
            ]
        );
        $user->sendEmailVerificationNotification();
        return $this->response(['user' => $user, 'access_token' => $user->createToken('users')->accessToken],200);
    }

    // Forget Password
    public function forgetPassword(RecoveryRequest $request)
    {
        $response =  Password::sendResetLink($request->only('email'));

        $msg = "Email could not be sent to this email address";
        $code = 400;
        if ($response == Password::RESET_LINK_SENT) {
            $msg = "Mail send successfully";
            $code = 400;
        }

        $this->response(['msg' => $msg],$code);
    }

    // Reset password
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

    // Verify Email
    public function verify($userID, Request $request) {
        if (!$request->hasValidSignature()) {
            return $this->response(['msg' => 'Invalid/Expired url provided.'],401);
        }
    
        $user = User::findOrFail($userID);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            User::where('id' , $user->id)->update(['is_email_verified' => true]);
            Merchant::where('email' , $user->email)->update(['is_email_verified' => true]);
        }
    
        return $this->response(['msg' => 'Email verified sucessfully'],200);
    }
    
    // Resend Email for verfification
    public function resend() {
        if (auth()->user()->hasVerifiedEmail()) {
            return $this->response(['msg' => 'Email already verified.'],401);
        }
    
        auth()->user()->sendEmailVerificationNotification();
    
        return $this->response(['msg' => 'Email verification link sent on your email id.'],200);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response(null,200);
    }
}
