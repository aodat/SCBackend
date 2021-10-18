<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;

use Illuminate\Http\Request;

use App\Http\Repositories\User\IUserRepo;

class UserController extends Controller
{
    protected $userRepo;
    public function __construct(
        IUserRepo $user
    )
    {
        $this->userRepo = $user;
    }

    public function login(AuthRequest $request) {
        $data = $this->userRepo->login($request->email,$request->password);
        $code = 200;
        if(isset($data['msg']))
            $code = 400;
        
        return $this->response($data,$code);
    }

    public function register(AuthRequest $request)
    {
        $data = $this->userRepo->register($request);
        return $this->response($data,200);
    }

    public function logout(Request $request)
    {
        $this->userRepo->logout($request);
        return $this->response(null,200);
    }

}
