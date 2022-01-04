<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodsRequest;
use App\Models\Merchant;

use App\Exceptions\InternalException;
use Carbon\Carbon;

class PaymentMethodsController extends Controller
{
    public function index(PaymentMethodsRequest $request)
    {
        $data = $request->validated();
        $merchant = Merchant::findOrFail($data['merchant_id']);
        return $this->response($merchant->payment_methods, 'Data Retrieved Successfully');
    }

    public function update(PaymentMethodsRequest $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? null;
        $data['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
        $merchant_id = $data['merchant_id'];

        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $payment_methods = $merchant->payment_methods;
        
        $payments = collect($payment_methods);
        $payment = $payments->where('id', $id);
        if ($payment->first() == null)
            throw new InternalException('Payment id not Exists');
        $current = $payment->keys()->first();
        $payment[$current] = $data;
        $payment_methods = $payments->replaceRecursive($payment);
        $merchant->update(['payment_methods' => $payment_methods]);
        return $this->successful('Updated Successfully');
    }
}
