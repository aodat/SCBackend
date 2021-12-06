<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Models\Merchant;

use App\Http\Requests\Merchant\PaymentMethodsRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class PaymentMethodsController extends MerchantController
{
    public function index(PaymentMethodsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id', $merchantID)->select('payment_methods')->first();
        return $this->response($data->payment_methods, 'Data Retrieved Successfully', 200);
    }

    public function store(PaymentMethodsRequest $request)
    {
      
        $json = $request->validated();
        $list = $this->getMerchentInfo();

        $PinCode = $this->cheakVerifyMerchantPhoneNumber($request->pin_code,  $list->id , 'payment_methods_create');
        if (!$PinCode)
        return $this->error('this code in correct');

        $result = collect($list->payment_methods);
        $counter = $result->max('id') ?? 0;
        $provider = collect($list->config['payment_providers'])->where('code', strtolower($json['provider_code']))->first();

        if ($provider == null)
            throw new InternalException('Provider Code Not Valid Or Not Available At This Country', 400);

        $json += $provider;
        $json['id'] = ++$counter;
        $json['created_at'] = Carbon::now();
        if (isset($json['pin_code']))
            unset($json['pin_code']);
        $list->update(['payment_methods' => $result->merge([$json])]);
        return $this->successful('Create Successfully');
    }

    public function delete($id,$pin_code, PaymentMethodsRequest $request)
    {
        $list = $this->getMerchentInfo();

        $PinCode = $this->cheakVerifyMerchantPhoneNumber($pin_code,  $list->id , 'payment_methods_delete');
        if (!$PinCode)
        return $this->error('this code in correct');
        $result = collect($list->payment_methods);
        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['payment_methods' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
