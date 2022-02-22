<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\API\Merchant\TransactionsController;
use App\Http\Controllers\Utilities\Shipcash;
use App\Http\Requests\Merchant\PaymentLinksRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\PaymentLinks;
use Carbon\Carbon;
use Throwable;
use Stripe;

class PaymentLinksController extends MerchantController
{
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

        $paymentLinks = PaymentLinks::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($statuses)) {
            $paymentLinks->whereIn('status', $statuses);
        }

        if ($operation) {
            $paymentLinks->where("amount", $operation, $value);
        } else if ($value) {
            $paymentLinks->whereBetween('amount', [intval($value), intval($value) . '.99']);
        }

        $tabs = DB::table('payment_links')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->groupBy('status')
            ->pluck('counter', 'status');
        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($paymentLinks->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
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

    public function delete($PaymentID, PaymentLinksRequest $request)
    {
        $PaymentInfo = PaymentLinks::where('id', $PaymentID)->first();
        if ($PaymentInfo->status != 'DRAFT') {
            return $this->error('You Cant Delete This Payment Link');
        }
        $PaymentInfo->delete();

        return $this->successful('Deleted Successfully');
    }

    public function charge(PaymentLinksRequest $request, TransactionsController $transaction)
    {
        $token = $request->token;
        $hash = $request->hash;

        $paymentInfo = PaymentLinks::where('hash', $hash)->where('status', '=', 'DRAFT')->first();

        $merchant = $this->getMerchantInfo($paymentInfo->merchant_id);

        try {
            // Stripe
            Stripe\Stripe::setApiKey(env('STRIPE_KEY'));
            Stripe\Charge::create([
                "amount" => Shipcash::exchange($paymentInfo->amount, $merchant->currency_code) * 100,
                "currency" => "USD",
                "source" => $token,
                "description" => "Payment From Shipcash : Merchant ID " . $paymentInfo->merchant_id . " / " . $merchant->name . " To " . $paymentInfo->customer_name,
            ]);

            $paymentInfo->status = 'PAID';
            $paymentInfo->paid_at = Carbon::now();
            $paymentInfo->save();

            $transaction->COD(
                'CASHIN',
                $paymentInfo->merchant_id,
                $paymentInfo->id,
                $paymentInfo->amount - $paymentInfo->fees,
                "INVOICE",
                $paymentInfo->user_id,
                'Invoice Payment Charged',
                'COMPLETED',
                'WEB'
            );
        } catch (Throwable $e) {

            return $this->error($e->getMessage());
        }

        return $this->successful('The Payment Successfully Charged');
    }
}
