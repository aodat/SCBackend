<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecoveryRequest;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class RecoveryController extends Controller
{

    public function forgetpassword(RecoveryRequest $request)
    {
        $response =  Password::sendResetLink($request->only('email'));
        if ($response == Password::RESET_LINK_SENT) {
            $message = "Mail send successfully";
        } else {
            $message = "Email could not be sent to this email address";
        }
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
            $message = "Password reset successfully";
        } else {
            $message = "Email could not be sent to this email address";
        }
        $response = ['data' => '', 'message' => $message];
        return response()->json($response);
    }
}
