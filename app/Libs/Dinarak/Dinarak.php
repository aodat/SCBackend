<?php

namespace Libs;

use Illuminate\Support\Facades\Http;
use App\Exceptions\InternalException;

class Dinarak
{
    private $endPoint = 'https://api.dinarak.com/v2.0';
    private $tokenKey;
    function __construct()
    {
        $this->tokenKey = $this->generatedKey();
    }

    private function generatedKey()
    {
        $loginData = [
            "username" => env('DINARAK_USERNAME'),
            "password" => env('DINARAK_PASSWORD'),
            "grant_type" => env('DINARAK_GRANT_TYPE')
        ];
        $response = Http::post($this->endPoint . "/token", $loginData);
        $this->tokenKey = json_decode($response)->access_token;
    }

    public function deposit($wallet_number, $amount, $transaction)
    {
        // Transfer 
        $transferData = [
            "Name" =>  auth()->user()->name,
            "Amount" => (float)$amount,
            "Description" => "Transfer from ShipCash",
            "ReceiverID" => (string)$wallet_number,
            "MessageId" => generateMessageID(),
            "OperationName" => "Transfer",
        ];

        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->endPoint . "/transfer/transfer", $transferData);

        $transaction->update(['notes' => json_encode($response->json())]);

        $status = json_decode($response)->status->id;
        if ($status == 1) {
            $transaction->update(['status' => 'confirmed']);
        }

        return true;
    }

    public function request($wallet_number, $amount, $pincode)
    {
        $this->pincode($wallet_number, $amount);

        $transferData = [
            'Name' => Request()->user()->merchant_id, ' - ', Request()->user()->name,
            'Amount' => $amount,
            'Description' => 'Request From Shipcash',
            'MessageId' => generateMessageID(),
            'WalletID' => $wallet_number,
            'OTP' => $pincode
        ];
        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->endPoint . "/transfer/deduct", $transferData);

        return true;
    }

    private function pincode($wallet_number, $amount)
    {
        $transferData = [
            'Amount' => $amount,
            'PhoneNumber' => $wallet_number
        ];
        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->endPoint . "/Services/PushOTP", $transferData);

        return true;
    }
}
