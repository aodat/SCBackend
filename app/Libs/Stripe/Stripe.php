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
    private static $DELETE_INVOICE = "https://api.stripe.com/v1/invoices";

    function __construct()
    {
        $this->access_key = env('STRIPE_ACCESS_KEY');
    }

    public function invoice($custmerID, $description, $amount)
    {
        $this->invoiceItem($custmerID, $description, $amount);

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->post(self::$CREATE_INVOICE, [
                'customer' => $custmerID
            ]);

        if (!$response->successful())
            throw new CarriersException('Stripe Create Invoice – Something Went Wrong');

        return [
            'fk_id' => $response->json()['id'],
        ];
    }

    public function invoiceItem($custmerID, $description, $amount)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->post(self::$INVOICE_ITEM, [
                'customer' => $custmerID,
                'description' => $description ?? '',
                'amount' => $amount * 1000,
                'currency' => 'USD'
            ]);

        if (!$response->successful())
            throw new CarriersException('Stripe Create Invoice Item – Something Went Wrong');

        return true;
    }

    public function finalizeInvoice($invoiceID)
    {
        $url = str_replace('##invoiceID##', $invoiceID, self::$FINALIZE_INVOICE);
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->post($url);

        if (!$response->successful())
            throw new CarriersException('Stripe Finalize Invoice – Something Went Wrong');
        return $response->json()['hosted_invoice_url'];
    }

    public function createCustomer($name, $email)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->post(self::$NEW_CUSTOMER_URL, [
                'name'  => $name,
                'email' => $email
            ]);

        if (!$response->successful())
            throw new CarriersException('Stripe Create Customer – Something Went Wrong');
        return $response->json()['id'];
    }

    public function deleteInvoice($invoiceID)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
            ->withToken($this->access_key)
            ->delete(self::$DELETE_INVOICE . '/' . $invoiceID);

        if (!$response->successful())
            throw new CarriersException('Stripe Delete Invoice – Something Went Wrong');

        return true;
    }
}
