<?php

namespace Libs;

use App\Exceptions\InternalException;
use Illuminate\Support\Facades\Http;
use \TelrGateway\TelrManager;

class Telr
{
    private $TELR_STORE_ID, $TELR_STORE_AUTH_KEY;
    private $ORDER_CREATION = "https://secure.telr.com/gateway/order.json";
    private $CREAT_TRANSACTION = "https://secure.telr.com/gateway/order.json";
    public function __construct()
    {

        $this->TELR_STORE_ID = env('TELR_STORE_ID'); //Your Store ID'
        $this->TELR_STORE_AUTH_KEY = env('TELR_STORE_AUTH_KEY'); //Your Authentication Key
    }

    /**
     *@return  OrderCreation It can use either the GET or POST method .
     *@param ($data) is array required to send  some info 
     */
    public function orderCreation($data)
    {
        $params = [
            'ivp_method'  => $data['ivp_method'], //create
            'ivp_store'   =>  $this->TELR_STORE_ID,
            'ivp_authkey' =>  $this->TELR_STORE_AUTH_KEY,
            'ivp_cart'    => $data['ivp_cart'], //UniqueCartID
            'ivp_test'    =>  $data['ivp_test'], //1
            'ivp_amount'  => $data['ivp_test'],
            'ivp_currency' => $data['ivp_currency'], //JOD
            'ivp_desc'    => $data['ivp_desc'], //Product Description
            'return_auth' => 'https://domain.com/return.html',
            'return_can'  => 'https://domain.com/return.html',
            'return_decl' => 'https://domain.com/return.html'
        ];

        $response = Http::withHeaders([
            'content-type' =>  'application/json'
        ])->post(
            $this->ORDER_CREATION,
            $params
        );
        $results = $response->json();
        $ref = trim($results['order']['ref']);
        $url = trim($results['order']['url']);
        if (empty($ref) || empty($url))
            throw new InternalException('Telr Failed To Create Order – Something Went Wrong', 400, $params, $response);
        if (!$response->successful())
            throw new InternalException('Telr Order Error – Something Went Wrong', 400, $params, $response);

        return ['url' =>  $url, 'ref' => $ref];
    }

    /**
     *@return transaction It can use either POST method (Response xml data).
     *@param ($data) is array required to send some info 
     */

    public function transaction($data)
    {
        $params = [
            'ivp_store'      =>  $this->TELR_STORE_ID,
            'ivp_authkey'     => $this->TELR_STORE_AUTH_KEY,
            'ivp_trantype'   => $data['ivp_trantype'], //  sale
            'ivp_tranclass'  => $data['ivp_tranclass'], //cont',
            'ivp_desc'       => $data['ivp_desc'], //Product Description
            'ivp_cart'       => $data['ivp_cart'], //Your Cart ID',
            'ivp_currency'   => $data['ivp_currency'], //JOD
            'ivp_amount'     => $data['ivp_amount'],
            'tran_ref'       => $data['tran_ref'], //12 digit reference of intial ecom/moto transaction
            'ivp_test'       => $data['ivp_test']
        ];
        $response = Http::withHeaders([
            'content-type' =>  'application/json'
        ])->post(
            $this->CREAT_TRANSACTION,
            $params
        );

        if (!$response->successful())
            throw new InternalException('Telr Transaction Error – Something Went Wrong', 400, $params, $response);


        return $response;
    }

    /**
     *@return MessageFormat It can use either POST method 
     *@param ($data) is array required to send some info 
     */
    public function MessageFormat($data)
    {

        $params = [
            'tran_store'      => $data['tran_store'],
            'tran_type'     => $data['tran_type'],
            'tran_class'   => $data['tran_class'], //  sale
            'tran_test'  => $data['tran_test'], //cont',
            'tran_ref'       => $data['tran_ref'], //Product Description
            'tran_prevref'       => $data['tran_prevref'], //Your Cart ID',
            'tran_firstref'   => $data['tran_firstref'], //JOD
            'tran_currency'     => $data['tran_currency'],
            'tran_amount'       => $data['tran_amount'], //12 digit reference of intial ecom/moto transaction
            'tran_cartid'       => $data['tran_cartid'],
            "tran_desc" => $data['tran_desc'],
            "tran_status" => $data['tran_status'],
            "tran_authcode" => $data['tran_authcode'],
            "tran_authmessage" => $data['tran_authmessage'],
            "tran_check" => $data['tran_check'],
            "bill_title" => $data['bill_title'],
            "bill_fname" => $data['bill_fname'],
            "bill_sname" => $data['bill_sname'],
            "bill_addr1" => $data['bill_addr1'],
            "bill_addr2" => $data['bill_addr2'],
            "bill_addr3" => $data['bill_addr3'],
            "bill_city" => $data['bill_city'],
            "bill_region" => $data['bill_region'],
            "bill_country" => $data['bill_country'],
            "bill_zip" => $data['bill_zip'],
            "bill_phone1" => $data['bill_phone1'],
            "bill_email" => $data['bill_email'],
            "xtra_" => $data['xtra_'],

        ];
        $response = Http::withHeaders([
            'content-type' =>  'application/json'
        ])->post(
            $this->CREAT_TRANSACTION,
            $params
        );

        if (!$response->successful())
            throw new InternalException('Telr Transaction Error – Something Went Wrong', 400, $params, $response);


        return $response;
    }
}

