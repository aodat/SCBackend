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
        $response = Http::get($this->endPoint . 'account/encrypt?Key=' . $this->agentKey);
        if (!$response->successful())
            throw new InternalException('Gate To Pay Key in valid', 500);
        return $response->key;
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
