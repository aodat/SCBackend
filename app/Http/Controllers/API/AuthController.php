<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\RecoveryRequest;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        $userData['token'] = $userData->createToken('users', [$userData->role])->accessToken;

        $userData['system_config'] = [
            'domastic' => $this->domastic(),
            'express' => $this->express(),
            'countries' => $this->countries()
        ];

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
        // $user->sendEmailVerificationNotification();
        return $this->successful('User Created Successfully');
    }

    public function changeSecret(ClientRepository $clientRepository)
    {

        $clients = Client::where('user_id', Auth::user()->merchant_id)->get();
        $clients->map(function ($client) use ($clientRepository) {
            $clientRepository->delete($client);
        });

        $client = $clientRepository->createPasswordGrantClient(
            Auth::user()->id,
            Auth::user()->name,
            'http://example.com/callback.php',
            str_replace(' ', '-', strtolower(Auth::user()->name))
        );
        $user = Merchant::find(Auth::user()->merchant_id)
            ->update(["secret_key" => $client->secret]);
        return $this->successful();
    }

    public function listClient(ClientRepository $clientRepository)
    {

        $clients = Client::where('user_id', Auth::user()->merchant_id)->get();
        $clients = $clientRepository->personalAccessClient(Auth::user()->merchant_id);
        return  $clients;
    }



    // Forget Password
    public function forgetPassword(RecoveryRequest $request)
    {
        $response =  Password::sendResetLink($request->only('email'));

        $msg = "Email could not be sent to this email address";
        $code = 400;
        if ($response == Password::RESET_LINK_SENT) {
            $msg = "Mail send successfully";
            $code = 200;
        }

        $this->response([], $msg, $code);
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

        $msg = "Email could not be sent to this email address";
        $code = 400;
        if ($response == Password::PASSWORD_RESET) {
            $msg = "Password reset successfully";
            $code = 200;
        }

        $this->response([], $msg, $code);
    }

    // Verify Email
    public function verifyEmail(Request $request)
    {
        if (!$request->hasValidSignature()) {
            return $this->response([], 'Invalid/Expired url provided', 401);
        }

        $user = User::findOrFail($request->id);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            User::where('id', $user->id)->update(['is_email_verified' => true]);
            Merchant::where('email', $user->email)->update(['is_email_verified' => true]);
        }
        return $this->response([], 'Email verified sucessfully', 200);
    }

    // Resend Email for verfification
    public function resend()
    {
        if (auth()->user()->hasVerifiedEmail())
            return $this->response([], 'Email already verified.', 200);

        auth()->user()->sendEmailVerificationNotification();

        return $this->response([], 'Email verification link sent on your email id.', 200);
    }

    // Get Merchant Secret Key
    public function getSecretKey(Request $request)
    {
        $merchant = Merchant::findOrFail($request->user()->merchant_id);
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
        $merchant = Merchant::findOrFail($request->user()->merchant_id);

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
        return $this->successful('Revoked Suceefully');
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response([], 'User Log Out.', 200);
    }
}
