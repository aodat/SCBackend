<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Http\Requests\AuthRequest;

use Illuminate\Support\Facades\Hash;

use App\Models\Merchant;
use App\Models\User;
class AuthController extends Controller
{
    /*
    protected $model;
    public function __construct(Post $post) 
    {
       $this->model = new General($post);
    }
    */

	public function login(AuthRequest $request) {
        $loginData = $request->validate([
            'email' => 'email|required',
            'password' => 'required'
        ]);

        if (!auth()->attempt($loginData)) {
            return response(['message' => 'This User does not exist, check your details'], 400);
        }

        $accessToken = auth()->user()->createToken('users')->accessToken;

        return response(['user' => auth()->user(), 'access_token' => $accessToken]);
    }

    public function register(AuthRequest $request)
    {
        $user = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'remember_token' => md5(time())
        ];
        User::create($user)->createToken('authToken')->accessToken;
        
        $merchant = [
            'name' => $request->name,
            'email' => $request->email,
            'type' => $request->type,
            'phone' => $request->phone,
        ];
        

        Merchant::create($merchant);

        return true;
        dd($request->json()->all());
    }

}
