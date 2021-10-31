<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Stripe
{
    private $access_key;

    private static $INVOICE_URL = 'https://api.stripe.com/v1/invoices';
    function __construct() {
        $this->access_key = config('carriers.stripe.key');
    }
    public function invoice()
    {

        // $client = new http\Client;
        // $request = new http\Client\Request;
        // $request->setRequestMethod('POST');
        // $body = new http\Message\Body;
        // $body->append(new http\QueryString(array(
        //     'customer' => 'cus_KVZY6KCMMtaEOp')
        // ));$request->setBody($body);
        // $request->setOptions(array());
        // $request->setHeaders(array(
        //     'Authorization' => 'Basic c2tfdGVzdF81MUpwWkpVSFpmWUlqUmVmUUNWZE5rYjgzRzF5YWZ5d0E4NE1jU1RIZDllb2l6RkE0ZTc2S2hoVXhaZ3dDa1Y2c21uc01ma2FOcnlHMEFTWVJzNDE3RkNnazAwUG5adndGcTA6',
        //     'Content-Type' => 'application/x-www-form-urlencoded'
        // ));
        // $client->enqueue($request)->send();
        // $response = $client->getResponse();
        // echo $response->getBody();



        $response = Http::withHeaders([
            'Authorization' => 'Basic c2tfdGVzdF81MUpwWkpVSFpmWUlqUmVmUUNWZE5rYjgzRzF5YWZ5d0E4NE1jU1RIZDllb2l6RkE0ZTc2S2hoVXhaZ3dDa1Y2c21uc01ma2FOcnlHMEFTWVJzNDE3RkNnazAwUG5adndGcTA6', // .$this->access_key
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->post(self::$INVOICE_URL,[
            'customer' => 'cus_KVZY6KCMMtaEOp'
        ]);

        // dd($response->json());
        dd($response);
    }
}