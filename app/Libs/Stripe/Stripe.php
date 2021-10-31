<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Stripe
{
    private $access_key;

    private static $NEW_CUSTOMER_URL = 'https://api.stripe.com/v1/customers';
    private static $INVOICE_ITEM = 'https://api.stripe.com/v1/invoiceitems';
    private static $CREATE_INVOICE = 'https://api.stripe.com/v1/invoices';
    private static $FINALIZE_INVOICE = 'https://api.stripe.com/v1/invoices/##invoiceID##/finalize';

    function __construct() {
        $this->access_key = config('carriers.stripe.key');
    }
    
    public function invoice($custmerID,$amount)
    {
        $this->invoiceItem($custmerID,$amount);

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->withToken($this->access_key)
        ->asForm()
        ->post(self::$CREATE_INVOICE,[
            'customer' => $custmerID
        ]);

        if (! $response->successful())
            throw new CarriersException('Stripe Create Invoice Item – Something Went Wrong');

        $receipt = $this->finalizeInvoice($response['id']);

        return [
            'fk_id' => $receipt['id'],
            'link' => $receipt['hosted_invoice_url']
        ];
    }

    public function invoiceItem($custmerID,$amount)
    {
        $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])
            ->withToken($this->access_key)
            ->asForm()
            ->post(self::$INVOICE_ITEM,[
                'customer' => $custmerID,
                'amount' => $amount,
                'currency' => 'USD'
            ]);

        if (! $response->successful())
            throw new CarriersException('Stripe Create Invoice Item – Something Went Wrong');

        return true;
    }

    public function finalizeInvoice($invoiceID)
    {
        $url = str_replace('##invoiceID##',$invoiceID,self::$FINALIZE_INVOICE);
        $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])
            ->withToken($this->access_key)
            ->asForm()
            ->post($url);

        if (! $response->successful())
            throw new CarriersException('Stripe Finalize Invoice – Something Went Wrong');

        return $response->json();
    }

    public function createCustomer($name,$email)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->withToken($this->access_key)
        ->asForm()
        ->post(self::$NEW_CUSTOMER_URL,[
            'name'  => $name,
            'email' => $email
        ]);

        if (! $response->successful())
            throw new CarriersException('Stripe Create Customer – Something Went Wrong');
        return $response->json()['id'];
    }
}