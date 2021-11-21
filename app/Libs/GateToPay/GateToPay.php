<?php

namespace Libs;

use Illuminate\Support\Facades\Http;
use App\Exceptions\InternalException;

class GateToPay
{

    private  $tokenKey, $agentKey;

    private $endPoint;

    public function __construct()
    {
        $this->endPoint = "https://cmsopenapitest.gatetopay.com/api/";
        if (env('APP_ENV') == 'local')
            $this->endPoint = "https://cmsopenapitest.gatetopay.com/api/";

        $this->agentKey = env('GATETOPAY_AGENTKEY');
        $this->tokenKey = $this->generatedKey();
    }

    protected function generatedKey()
    {
        // $response = Http::get($this->endPoint . 'account/encrypt?Key=' . $this->agentKey);
        // if (!$response->successful())
        //     throw new InternalException('Gate To Pay Key in valid', 500);
        return  'e9xLc/rigzzNlLGON4hdFvp6/6XFjBL7f34GWLzfD3vqwTkfxxcOWMf/uk6VcF2eEGSSQjn6pAyUKqqtC+NvnUULTjg13aNGnZIKJphNZ3A7n//vZ7zEr22kieik9CxExN6JBRYLMPs4qpgLMtkAgIN10kWmecXLryiHWFrz7isfqEI6HTUH0EW7/MmlKwbGvAEqnUVPrKuKM4x/mTdihMbhSIQ25OM56hSUvBaVzX6jMR3F9nXEZI7EKXt//DK34q7HdIAASUj0oAOJkcXgM0Izt/gAF9+joBj7JUIA5LzJq6O25ZyC7Q+g1JbnRou9DJwBOIovsZanp0RTjcYouA=='; // $response->key;
    }

    /*

    public function getCustomerCards($customerId)
    {
        $this->Url = 'Broker/GetCustomerCards?customerId=' . $customerId;
        return $this->httpRequest();
    }
    */

    public function cardDeposit($customerId, $cardId, $cardExpiryDate, $depositAmount)
    {
        $response = Http::withHeaders([
            'Key' => $this->tokenKey,
            'AgentKey' => $this->agentKey
        ])->post($this->endPoint . 'Broker/CardDeposit', [
            'customerId' => $customerId,
            'cardId' => $cardId,
            'depositAmount' => $depositAmount,
            'currency' => 'JOD',
            'transactionId' => floor(time() - 999999999),
            'cardExpiryDate' => $cardExpiryDate
        ]);
        if ($response->successful())
            throw new InternalException('Deposit transaction error', 500);

        return $response->transactionId;
    }

    public function cardWithdrawal($customerId, $cardId, $cardExpiryDate, $withdrawalAmount)
    {
        $response = Http::withHeaders([
            'Key' => $this->tokenKey,
            'AgentKey' => $this->agentKey
        ])->post($this->endPoint . 'Broker/CardWithdrawal', [
            'customerId' => $customerId,
            'cardId' => $cardId,
            'withdrawalAmount' => $withdrawalAmount,
            'currency' => 'JOD',
            'transactionId' => floor(time() - 999999999),
            'cardExpiryDate' => $cardExpiryDate
        ]);
        if ($response->successful())
            throw new InternalException('WithDraw transaction error', 500);

        return $response->transactionId;
    }

    public function cardTransaction()
    {
    }
}
