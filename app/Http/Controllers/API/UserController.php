<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;

use Illuminate\Http\Request;
use App\Http\responseHandler;

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
        if(empty($data))
            $code = 400;
        
        return responseHandler::response($data,$code);
    }

    public function register(AuthRequest $request)
    {
        $data = $this->userRepo->register($request);
        return responseHandler::response($data,200);
    }

    public function logout(Request $request)
    {
        $this->userRepo->logout($request);
        return responseHandler::response(null,200);
    }

}
