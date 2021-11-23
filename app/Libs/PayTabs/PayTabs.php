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

    public function transaction($card_id, $amount, $transaction_type, $transacton_ref, $currancy = 'JOD')
    {
        $response = Http::withHeaders([
            'authorization' => $this->server,
            'content-type' =>  'application/json'
        ])->post(
            $this->endPoint,
            [
                "profile_id" => $this->profileID,
                "tran_type" => $transaction_type,
                "tran_ref" => $transacton_ref,
                "cart_id" => $card_id,
                "cart_description" => "Order " . $transacton_ref,
                "cart_currency" => $currancy,
                "cart_amount" => $amount
            ]
        );

        if (!$response->successful())
            throw new InternalException('Paytabs Transaction Error', 500);

        return true;
    }
}
