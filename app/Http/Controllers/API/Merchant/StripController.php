<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Invoices;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class StripController extends Controller
{

    public function strip($shipmentID, Request $request)
    {
        $invoice = Invoices::where('fk_id', $shipmentID)
            ->where('status', '<>', 'PAID')
            ->first();
        if(!$invoice)
            return Redirect::to('https://beta.shipcash.net');
        return view('strip.payment_form')->with('invoice', $invoice);
    }

    public function stripePost(Request $request)
    {
        $invoice = Invoices::findOrFail($request->in_id);
        $merchant = Merchant::findOrFail($invoice->merchant_id);
        try {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            Stripe\Charge::create([
                "amount" => currency_exchange($invoice->amount, 'JOD') * 100,
                "currency" => "usd",
                "source" => $request->stripeToken,
                "description" => "Payment From Shipcash : Merchant ID " . $invoice->merchant_id . " / " . $merchant->name . " To " . $invoice->customer_name,
            ]);

            Transaction::create(
                [
                    'type' => 'CASHIN',
                    'subtype' => 'COD',
                    'item_id' => $request->in_id,
                    'merchant_id' => $invoice->merchant_id,
                    'source' => 'INVOICE',
                    'status' => 'PROCESSING',
                    'created_by' => Request()->user()->id,
                    'balance_after' => $invoice->amount,
                    'amount' => $invoice->amount,
                    'resource' => 'API',
                ]
            );
        } catch (Throwable $e) {
            $invoice->status = 'FAILED';
            $invoice->save();
            return Redirect::back()->withErrors(['message' => 'Unexpected Error , Please Try Again']);
        }

        $invoice->status = 'PAID';
        $invoice->save();

        return redirect()->back()->with('message', 'Your Payment Transaction Successfully');
    }
}
