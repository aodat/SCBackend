<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodsRequest;
use App\Models\Merchant;
use Carbon\Carbon;

class PaymentMethodsController extends Controller
{

    private $merchant;
    public function __construct(PaymentMethodsRequest $request)
    {
        $this->merchant = Merchant::findOrFail($request->merchant_id);
    }

    public function index(PaymentMethodsRequest $request)
    {
        return $this->response($this->merchant->payment_methods, "Data Retrieved Successfully");
    }

    public function update(PaymentMethodsRequest $request)
    {
        $merchant = $this->merchant;
        $data = $request->validated();

        unset($data['merchant_id']);

        $payment_methods = $merchant->payment_methods;

        $methods = collect($payment_methods);
        $methods = $methods->where('id', $request->id);

        if ($methods->first() == null) {
            throw new InternalException('Payment ID not Exists');
        }

        $current = $methods->keys()->first();
        $payment_methods[$current]['iban'] = $request->iban;
        $payment_methods[$current]['name'] = $request->name;
        $payment_methods[$current]['provider_code'] = $request->provider_code;
        $payment_methods[$current]['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');

        $merchant->update(['payment_methods' => $payment_methods]);

        return $this->successful('Updated Successfully');
    }
}
