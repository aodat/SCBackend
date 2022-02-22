<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Utilities\Shipcash;
use App\Http\Requests\Merchant\PaymentLinksRequest;
use App\Models\PaymentLinks;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Stripe;
class PaymentLinksController extends MerchantController
{
    protected $stripe;
    private $status = [
        'DRAFT' => 0, 'PAID' => 0, 'FAILED' => 0,
    ];

    public function index(PaymentLinksRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $statuses = $filters['statuses'] ?? [];
        $value = $filters['amount']['val'] ?? null;
        $operation = $filters['amount']['operation'] ?? null;

        $invoices = PaymentLinks::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($statuses)) {
            $invoices->whereIn('status', $statuses);
        }

        if ($operation) {
            $invoices->where("amount", $operation, $value);
        } else if ($value) {
            $invoices->whereBetween('amount', [intval($value), intval($value) . '.99']);
        }

        $tabs = DB::table('payment_links')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->groupBy('status')
            ->pluck('counter', 'status');
        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($invoices->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
    }

    public function store(PaymentLinksRequest $request)
    {
        $data = $request->validated();

        $data['hash'] = Str::uuid();
        $data['merchant_id'] = $request->user()->merchant_id;
        $data['user_id'] = $request->user()->id;
        $data['resource'] = Request()->header('agent') ?? 'API';

        $data['fees'] = $data['amount'] * 0.05;
        PaymentLinks::create($data);

        return $this->successful('Created Successfully');
    }

    public function show($id, PaymentLinksRequest $request)
    {
        $data = PaymentLinks::findOrFail($id);
        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function hash($hash)
    {
        $data = PaymentLinks::where('hash', $hash)->where('status', '=', 'DRAFT')->first();
        $merchant = $this->getMerchantInfo($data['merchant_id']);

        $data['amount'] = Shipcash::exchange($data['amount'], $merchant->currency_code);
        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function delete($invoiceID, PaymentLinksRequest $request)
    {
        $invoiceInfo = PaymentLinks::where('id', $invoiceID)->first();
        if ($invoiceInfo->status != 'DRAFT') {
            return $this->error('you cant delete this invoice');
        }
        $invoiceInfo->delete();

        return $this->successful('Deleted Successfully');
    }

    public function charge(PaymentLinksRequest $request)
    {
        $token = $request->token;
        $hash = $request->hash;

        $paymentInfo = PaymentLinks::where('hash', $hash)->where('status', '=', 'DRAFT')->first();

        $merchant = $this->getMerchantInfo($paymentInfo->merchant_id);

        try {
            // Stripe
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            Stripe\Charge::create([
                "amount" => Shipcash::exchange($paymentInfo->amount, $merchant->currency_code) * 100,
                "currency" => "usd",
                "source" => "tok_visa",
                "description" => "Payment From Shipcash : Merchant ID " . $paymentInfo->merchant_id . " / " . $merchant->name . " To " . $paymentInfo->customer_name,
            ]);

            $paymentInfo->status = 'PAID';
            $paymentInfo->paid_at = Carbon::now();
            $paymentInfo->save();
        } catch (Throwable $e) {
            return $this->error('Invalid Strip Token');
        }

        return $this->successful('The Payment Successfully Charged');
    }
}
