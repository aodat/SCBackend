<?php

namespace Libs;

use App\Exceptions\InternalException;
use Illuminate\Support\Facades\Http;

class Dinarak
{
    private $endPoint = 'https://api.dinarak.com/v2.0';
    private $tokenKey;
    public function __construct()
    {
        $this->tokenKey = $this->generatedKey();
    }

    private function generatedKey()
    {
        $loginData = [
            "username" => env('DINARAK_USERNAME'),
            "password" => env('DINARAK_PASSWORD'),
            "grant_type" => env('DINARAK_GRANT_TYPE'),
        ];
        $response = Http::post($this->endPoint . "/token", $loginData);
        return $response->json()['access_token'];
    }

    public function withdraw($merchecntInfo, $wallet_number, $amount)
    {
        // Transfer
        $transferData = [
            "Name" => $merchecntInfo->name,
            "Amount" => (float) $amount,
            "Description" => "Transfer from ShipCash",
            "ReceiverID" => (string) $wallet_number,
            "MessageId" => $this->generateMessageID(),
            "OperationName" => "Transfer",
        ];

        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->endPoint . "/transfer/transfer", $transferData);

        return $response;
    }

    public function deposit($merchecntInfo, $wallet_number, $amount, $pincode)
    {
        $transferData = [
            'Name' => $merchecntInfo->id, ' - ', $merchecntInfo->name,
            'Amount' => $amount,
            'Description' => 'Request From Shipcash',
            'MessageId' => $this->generateMessageID(),
            'WalletID' => $wallet_number,
            'OTP' => $pincode,
        ];
        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->endPoint . "/transfer/deduct", $transferData);
        if (!$response->successful()) {
            throw new InternalException('Dinark - Deposit Payment', $response->status());
        }

        return true;
    }

    public function pincode($wallet_number, $amount)
    {
        $transferData = [
            'Amount' => $amount,
            'PhoneNumber' => $wallet_number,
        ];
        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->endPoint . "/Services/PushOTP", $transferData);

        if (!$response->successful()) {
            throw new InternalException('Dinark - Pin Code Error', $response->status());
        }
    }

    public function generateMessageID()
    {
        $prefix = array_map(function ($chr) {
            return 9-+$chr;
        }, str_split(intval((microtime(1) * 10000))));
        $prefix = implode('', $prefix);
        return str_replace('.', '', uniqid($prefix, true));
    }
}
