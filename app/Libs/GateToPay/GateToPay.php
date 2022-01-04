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
        if (config('app.env') == 'production')
            $this->endPoint = "https://cmsopenapi.gatetopay.com/api/";

        $this->agentKey = env('GATETOPAY_AGENTKEY');
        $this->tokenKey = $this->generatedKey();
    }

    protected function generatedKey()
    {
        $response = Http::get($this->endPoint . 'account/encrypt?Key=' . $this->agentKey);
        if (!$response->successful())
            throw new InternalException('Gate To Pay Key in valid', 400, ['key' => $this->agentKey]);
        return $response->json();
    }

    public function cardDeposit($customerId, $cardId, $cardExpiryDate, $depositAmount)
    {
        $request = [
            'customerId' => $customerId,
            'cardId' => $cardId,
            'depositAmount' => $depositAmount,
            'currency' => 'JOD',
            'transactionId' => floor(time() - 999999999),
            'cardExpiryDate' => $cardExpiryDate
        ];

        $response = Http::withHeaders([
            'Key' => $this->tokenKey,
            'AgentKey' => $this->agentKey
        ])->post($this->endPoint . 'Broker/CardDeposit', $request);

        if (!$response->successful() || !$response->json()['isSuccess'])
            throw new InternalException('Deposit transaction error', 400, $request, $response);
        return $response->transactionId;
    }

    public function cardWithdrawal($customerId, $cardId, $cardExpiryDate, $withdrawalAmount)
    {
        $request =  [
            'customerId' => $customerId,
            'cardId' => $cardId,
            'withdrawalAmount' => $withdrawalAmount,
            'currency' => 'JOD',
            'transactionId' => floor(time() - 999999999),
            'cardExpiryDate' => $cardExpiryDate
        ];
        $response = Http::withHeaders([
            'Key' => $this->tokenKey,
            'AgentKey' => $this->agentKey
        ])->post($this->endPoint . 'Broker/CardWithdrawal', $request);
        if (!$response->successful() || !$response->json()['isSuccess'])
            throw new InternalException('WithDraw transaction error', 400, $request, $response);

        return $response->transactionId;
    }

    public function cardTransaction()
    {
    }
}
