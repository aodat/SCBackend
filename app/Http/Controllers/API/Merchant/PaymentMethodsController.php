<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Models\Merchant;

use App\Http\Requests\Merchant\PaymentMethodsRequest;
use AWS\CRT\HTTP\Request;
use Carbon\Carbon;

class PaymentMethodsController extends MerchantController
{
    public function index(PaymentMethodsRequest $request)
    {
        $merchantID =Request()->user()->merchant_id;
        $data = Merchant::where('id', $merchantID)->select('payment_methods')->first();
        return $this->response($data->payment_methods, 'Data Retrieved Successfully', 200);
    }

    public function store(PaymentMethodsRequest $request)
    {
        $json = $request->validated();
        $list = $this->getMerchentInfo();

        $result = collect(Merchant::where('id', $list->id)->payment_methods);
        $counter = $result->max('id') ?? 1;
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

    public function delete($id, PaymentMethodsRequest $request)
    {
        $list = $this->getMerchentInfo();
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
