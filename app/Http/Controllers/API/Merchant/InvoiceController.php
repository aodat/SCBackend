<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\InvoiceRequest;
use App\Jobs\StripeUpdates;
use App\Models\Invoices;

use Libs\Stripe;

class InvoiceController extends MerchantController
{
    protected $stripe;
    public function __construct()
    {
        $this->stripe = new Stripe();
    }

    public function store(InvoiceRequest $request)
    {
        $data = $request->validated();

        // on create invoice you can build it by stripe
        $customerID = $this->stripe->createCustomer($data['customer_name'], $data['customer_email']);
        $receipt = $this->stripe->invoice($customerID, $data['description'], $data['amount']);

        $data['fk_id'] = $receipt['fk_id'];
        $data['merchant_id'] = $request->user()->merchant_id;
        $data['user_id'] = $request->user()->id;
        $data['resource'] = $request->resource;
        Invoices::create($data);

        return $this->successful();
    }

    public function finalize($invoiceID, InvoiceRequest $request)
    {
        $invoiceInfo = Invoices::where('id', $invoiceID)->where('merchant_id', $request->user()->merchant_id)->first();

        $link = $this->stripe->finalizeInvoice($invoiceInfo->fk_id);
        $invoiceInfo->link = $link;
        $invoiceInfo->save();

        $this->response(['link' => $link]);
    }

    public function delete($invoiceID, InvoiceRequest $request)
    {
        $invoiceInfo = Invoices::where('id', $invoiceID)->where('merchant_id', $request->user()->merchant_id)->first();
        if ($invoiceInfo->status != 'DRAFT')
            return $this->error('you cant delete this invoice');
        $this->stripe->deleteInvoice($invoiceInfo->fk_id);
        $invoiceInfo->delete();


        return $this->successful('Deleted Sucessfully');
    }

    public function stripeProcessSQS(InvoiceRequest $request)
    {
        StripeUpdates::dispatch($request->json()->all());
        return $this->successful('Webhook Completed');
    }
}
