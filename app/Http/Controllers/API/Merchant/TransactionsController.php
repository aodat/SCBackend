<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Transaction\TransactionRequest;

use App\Models\Transaction;
class TransactionsController extends MerchantController
{
    public function index(TransactionRequest $request)
    {

    }

    public function getTransaction($id,TransactionRequest $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response(['msg' => 'Transaction Retrived Sucessfully','data' => $data],200);
    }
    
    public function withDraw($id,TransactionRequest $request)
    {
        $merchecntInfo = $this->getMerchentInfo($request->user()->merchant_id);
        
        $actualBalance = $merchecntInfo->actual_balance;
        $paymentMethod = $merchecntInfo->payment_methods;
        $paymentMethodID = $request->payment_method_id;

        if($actualBalance < $request->amount)
            return $this->response(['msg' => 'The Actual Balance Not Enough'],500);

        $merchecntInfo->actual_balance = $actualBalance - $request->amount;
        $merchecntInfo->save();

        $selectedPayment = collect($paymentMethod)->reject(function ($value) use($paymentMethodID) {
            if($value['id'] != $paymentMethodID)
                return $value;
        });
        $selectedPayment = array_values($selectedPayment->toArray());

        $transaction = Transaction::findOrFail($id);
        $transaction->balance_after = $request->amount;
        $transaction->payment_method = collect($selectedPayment);
        $transaction->save();

        return $this->response(null,204);
    }

    public function export(TransactionRequest $request)
    {

    }
}