<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\InvoiceRequest;
use App\Jobs\StripeUpdates;
use App\Models\Invoices;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Libs\Stripe;
use Stripe\Invoice;
use App\Http\Controllers\Utilities\Shipcash;

class InvoiceController extends MerchantController
{
    protected $stripe;
    private $status = [
        'DRAFT' => 0, 'PAID' => 0, 'FAILED' => 0, 'RENTURND' => 0,
    ];

    public function __construct()
    {
        $this->stripe = new Stripe();
    }

    public function index(InvoiceRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');
        $statuses = $filters['statuses'] ?? [];

        $invoices = Invoices::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($statuses)) {
            $invoices->whereIn('status', $statuses);
        }

        $tabs = DB::table('invoices')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->groupBy('status')
            ->pluck('counter', 'status');
        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($invoices->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
    }

    public function show($id, InvoiceRequest $request)
    {
        $data = Invoices::findOrFail($id);
        return $this->response($data, 'Data Retrieved Sucessfully');
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
        $data['resource'] = Request()->header('agent') ?? 'API';
        Invoices::create($data);

        return $this->successful('Created Successfully');
    }

    public function finalize($invoiceID, InvoiceRequest $request)
    {
        $invoiceInfo = Invoices::where('id', $invoiceID)->first();

        $link = $this->stripe->finalizeInvoice($invoiceInfo->fk_id);
        $invoiceInfo->link = $link;
        $invoiceInfo->save();

        $this->response(['link' => $link], 'Data Retrieved Successfully');
    }

    public function delete($invoiceID, InvoiceRequest $request)
    {
        $invoiceInfo = Invoices::where('id', $invoiceID)->first();
        if ($invoiceInfo->status != 'DRAFT') {
            return $this->error('you cant delete this invoice');
        }

        $this->stripe->deleteInvoice($invoiceInfo->fk_id);
        $invoiceInfo->delete();

        return $this->successful('Deleted Successfully');
    }

    public function stripeProcessSQS(InvoiceRequest $request)
    {
        StripeUpdates::dispatch($request->json()->all());
        return $this->successful('Webhook Completed');
    }
}
