<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Requests\AuthRequest;

use Illuminate\Support\Facades\Hash;

use App\Models\Merchant;
use App\Models\User;

class AuthController extends Controller
{
    public function login(AuthRequest $request) {
        if (!auth()->attempt(['email' => $request->email,'password' => $request->password])) {
            return $this->response(['msg' => 'Invalid Email or Password'],400);
        }
        $userData = auth()->user();
        return $this->response(['user' => $userData, 'access_token' => $userData->createToken('users')->accessToken],200);
    }

    public function register(AuthRequest $request)
    {
        $user = User::create(
            [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone
            ]
        );
        $user->sendEmailVerificationNotification();
        Merchant::create(
            [
                'name' => $request->name,
                'email' => $request->email,
                'type' => $request->type,
                'phone' => $request->phone
            ]
        );
        return $this->response(['user' => $user, 'access_token' => $user->createToken('users')->accessToken],200);
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response(null,200);
    }

}
