<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\RecoveryRequest;
use App\Jobs\Send;
use App\Jobs\SendMails;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

use App\Models\Merchant;
use App\Models\User;

use Illuminate\Support\Facades\Route;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class AuthController extends Controller
{
    public function login(AuthRequest $request)
    {
        if (!auth()->attempt(['email' => $request->email, 'password' => $request->password, 'status' => 'active'])) {
            return $this->error('Invalid Email or Password', 400);
        }

        $userData = auth()->user();

        if ($userData->merchant_id) {
            $merchant = Merchant::find($userData->merchant_id);
            if (!$merchant->is_active)
                return $this->error('Mechant Is In-Active', 403);
        }
        if ($userData->role === "member")
            $role = explode(",", $userData->role_member);
        $role[] = $userData->role;
        $userData['token'] = $userData->createToken('users', $role)->accessToken;
        return $this->response(
            $userData,
            'User Login Successfully',
            200
        );
    }


    public function register(AuthRequest $request, ClientRepository $clientRepository)
    {
        $merchant = Merchant::create(
            [
                'name' => $request->name,
                'email' => $request->email,
                'type' => $request->type,
                'phone' => $request->phone,
                'country_code' => $request->country_code,
                'currency_code' => ($request->country_code == 'JO') ? 'JOD' : 'SAR',
                'domestic_rates' => collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'), true)),
                'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true))
            ]
        );
        $user = User::create(
            [
                'merchant_id' => $merchant->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'is_owner' => true,
                'role' => 'admin'
            ]
        );

        $client = $clientRepository->createPasswordGrantClient(
            $merchant->id,
            $merchant->name,
            'http://example.com/callback.php',
            str_replace(' ', '-', strtolower($merchant->name))
        );
        $merchant->update(["secret_key" => $client->secret]);

        Send::dispatch($user);

        return $this->successful('User Created Successfully');
    }

    public function changeSecret(ClientRepository $clientRepository)
    {
        $merchantInfo = Merchant::findOrFail(Request()->user()->merchant_id);

        $clients = Client::where('user_id', Request()->user()->merchant_id)->get();
        $clients->map(function ($client) use ($clientRepository) {
            $clientRepository->delete($client);
        });

        $client = $clientRepository->createPasswordGrantClient(
            $merchantInfo->id,
            $merchantInfo->name,
            'http://example.com/callback.php',
            str_replace(' ', '-', strtolower($merchantInfo->name))
        );

        $merchantInfo->secret_key = $client->secret;
        $merchantInfo->save();
        return $this->successful('Secret Created Successfully');
    }

    // Forget Password
    public function forgetPassword(RecoveryRequest $request)
    {
        $response =  Password::sendResetLink($request->only('email'));

        if ($response == Password::RESET_LINK_SENT)
            return $this->successful('Mail send successfully');
        return $this->error('Email could not be sent to this email address');
    }

    // Reset password
    protected function resetPassword(RecoveryRequest $request)
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
        if ($response == Password::PASSWORD_RESET)
            return $this->successful('Password reset successfully');
        return $this->error('Email could not be sent to this email address');
    }

    // Verify Email
    public function verifyEmail(Request $request)
    {
        if (!$request->hasValidSignature()) {
            return $this->error('Invalid/Expired url provided', 400);
        }

        $user = User::findOrFail($request->id);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            User::where('id', $user->id)->update(['is_email_verified' => true]);
            Merchant::where('email', $user->email)->update(['is_email_verified' => true]);
        }
        return $this->successful('Email verified Successfully');
    }

    // Resend Email for verfification
    public function resend()
    {
        if (auth()->user()->hasVerifiedEmail())
            return $this->error('Email already verified.', 400);

        Send::dispatch(auth()->user());
        return $this->successful('Check your email');
    }

    // Get Merchant Secret Key
    public function getSecretKey(Request $request)
    {
        $merchant = Merchant::findOrFail(Request()->user()->merchant_id);
        $client = Client::where('user_id', $merchant->id)->where('revoked', false)->first();

        if ($client == null)
            $this->error('No Secret Key Created Yet');

        $request->request->add([
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $merchant->secret_key,
            'redirect_uri' => 'http://example.com/callback.php',
            'code' => ''
        ]);

        $proxy = Request::create(
            'oauth/token',
            'POST'
        );
        $result = json_decode(Route::dispatch($proxy)->getContent());
        return $this->response(['secret_key' => $merchant->secret_key, 'access_key' => $result->access_token], 'Access Key Retrieved Successfully');
    }

    // Genrate Access Token
    function generateSecretKey(Request $request, ClientRepository $clientRepository)
    {
        $merchant = Merchant::findOrFail(Request()->user()->merchant_id);

        $clients = Client::where('user_id', $merchant->id)->get();
        $clients->map(function ($client) use ($clientRepository) {
            $clientRepository->delete($client);
        });

        $client = $clientRepository->createPasswordGrantClient(
            $merchant->id,
            $merchant->name,
            'http://example.com/callback.php',
            str_replace(' ', '-', strtolower($merchant->name))
        );
        $merchant->secret_key = $client->secret;
        $merchant->save();

        return $this->successful();
    }

    // Delete all Clients Secret
    public function revokeSecretKey(Request $request, ClientRepository $clientRepository)
    {
        $clients = Client::where('user_id', $request->user()->merchant_id)->get();
        $clients->map(function ($client) use ($clientRepository) {
            $clientRepository->delete($client);
        });

        $merchant = Merchant::findOrFail(Request()->user()->merchant_id);
        $merchant->secret_key = null;
        $merchant->save();

        return $this->successful('Revoked Suceefully');
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->successful('User Log Out');
    }
}
