<?php 
namespace App\Http\Repositories\User;

use Illuminate\Support\Facades\Hash;
use App\Http\Repositories\Merchant\IMerchantRepo;

use App\Models\User;

class DBUserRepo implements IUserRepo
{   

    protected $merchantRepo;
    public function __construct(
        IMerchantRepo $merchant
    )
    {
        $this->merchantRepo = $merchant;
    }
    public function login($email , $password)
    {
        if (!auth()->attempt(['email' => $email,'password' => $password])) {
            return ['msg' => 'Invalid Email or Password'];
        }
        $userData = auth()->user();
        return ['user' => $userData, 'access_token' => $userData->createToken('users')->accessToken];
    }

    public function register($data)
    {
        $user = $this->createUser(
            [
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'remember_token' => md5(time())
            ]
        );
        $user->sendEmailVerificationNotification();
        $this->merchantRepo->create(
            [
                'name' => $data->name,
                'email' => $data->email,
                'type' => $data->type,
                'phone' => $data->phone
            ]
        );

        $userData = User::findOrFail($user->id);
        return ['user' => $userData, 'access_token' => $userData->createToken('users')->accessToken];

    }

    public function logout($data)
    {
        return $data->user()->token()->revoke();
    }

    public function getUseriInfo($userID)
    {
        return User::findOrFail($userID);
    }

    public function createUser($data)
    {
        return User::create($data);
    } 
}