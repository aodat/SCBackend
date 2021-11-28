<?php

namespace Libs;

use Illuminate\Support\Facades\Http;
use App\Exceptions\InternalException;

use Illuminate\Support\Str;

class PayTabs
{

    private $server, $client;
    private $profileID;
    private $endPoint;

    public function __construct()
    {
        $this->endPoint = "https://secure-jordan.paytabs.com/payment/request/";
        $this->server = env('PAYTABS_SERVER_KEY');
        $this->client = env('PAYTABS_CLIENT_KEY');
        $this->profileID = env('PAYTABS_PROFILE_ID');
    }

    public function transaction($amount, $transaction_type, $transacton_ref = '', $currancy = 'JOD')
    {
        $response = Http::withHeaders([
            'authorization' => $this->server,
            'content-type' =>  'application/json'
        ])->post(
            $this->endPoint,
            [
                'profile_id' => $this->profileID,
                'tran_type' => $transaction_type,
                'tran_class' => 'ecom',
                'tran_ref' => $transacton_ref,
                'cart_id' => $this->client,
                'cart_description' => "Order",
                'cart_currency' => $currancy,
                'cart_amount' => $amount,
                'return' => 'none'
            ]
        );

        if (!$response->successful())
            throw new InternalException('Paytabs Transaction Error', 400);

        return [
            'payment_url' => $response->json()['redirect_url'],
            'transaction_ref' => $response->json()['tran_ref'],
        ];
    }
}
