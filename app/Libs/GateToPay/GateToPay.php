<?php

namespace Libs;

use Illuminate\Support\Facades\Http;
use App\Exceptions\CarriersException;
use App\Exceptions\InternalException;

class GateToPay
{

    private  $tokenKey, $AgentKey;
    private  $basUrl = "https://cmsopenapitest.gatetopay.com/api/";
    private  $Url;

    function __construct()
    {
        $this->AgentKey = env('GATETOPAY_AGENTKEY');
        $this->tokenKey = $this->generatedKey();
    }

    protected function generatedKey()
    {
        $response = Http::get(self::$basUrl . '/api/account/encrypt?Key=' . self::$AgentKey);
        return  $response->key;
    }
    public function getCustomerCards($customerId)
    {
        $this->Url = 'Broker/GetCustomerCards?customerId=' . $customerId;
        return $this->httpRequest();
    }

    public function cardDeposit($data)
    {
        $this->Url = self::$basUrl . 'Broker/CardDeposit';
        $this->data = $data;
        return  $this->httpRequest('post');
    }

    public function cardWithdrawal($data)
    {
        $data['withdrawalAmount'] = 0;
        $this->Url = self::$basUrl . 'Broker/CardWithdrawal';
        $this->data = $data;
        return  $this->httpRequest('post');
    }
    public function getInquireTransaction($transactionId)
    {
        $this->Url = 'Inquire/InquireTransaction?transactionId=' . $transactionId;
        return   $this->httpRequest();
    }

    private function httpRequest($Method = "get")
    {

        if ($Method == "get")
            $response = Http::withHeaders([
                'Key' => self::$tokenKey,
                'AgentKey' => self::$AgentKey
            ])->get($this->Url);
        elseif ($Method == "post")
            $response = Http::withHeaders([
                'Key' => self::$tokenKey,
                'AgentKey' => self::$AgentKey
            ])->post($this->Url, $this->data);

        $response = $this->statusCode($response);
        return $response;
    }

    private function  statusCode($response)
    {
        if ($response->successful())
            return $response->json();
        elseif ($response->clientError())
            throw new InternalException('client Error , we need check value or anther');
        elseif ($response->serverError())
            throw  new InternalException('Server Error , 500 ', 500);
        elseif ($response->failed())
            throw  new InternalException('Error status up 400', 400);
    }
}
