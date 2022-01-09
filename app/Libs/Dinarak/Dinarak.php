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

    public function deposit($wallet_number, $amount)
    {
        // Transfer 
        $transferData = [
            "Name" =>  auth()->user()->name,
            "Amount" => (float)$amount,
            "Description" => "Transfer from ShipCash",
            "ReceiverID" => (string)$wallet_number,
            "MessageId" => static::generateMessageID(),
            "OperationName" => "Transfer",
        ];

        $response = Http::withToken($this->tokenKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->endPoint . "/transfer/transfer", $transferData);

        $this->transaction()->update(['notes' => json_encode($response->json())]);

        $status = json_decode($response)->status->id;
        if ($status == 1) {
            $this->update(['status' => 'confirmed']);
        }
    }


    public static function generateMessageID()
    {
        $prefix = array_map(function ($chr) {
            return 9 - +$chr;
        }, str_split(intval((microtime(1) * 10000))));
        $prefix = implode('', $prefix);
        return str_replace('.', '', uniqid($prefix, true));
    }
}
