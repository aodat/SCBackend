<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;

use App\Http\Requests\Merchant\PaymentMethodsRequest;

class PaymentMethodsController extends MerchantController
{
    public function getPaymentMethods(PaymentMethodsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('payment_methods')->first();

      
        if(collect($data->payment_methods)->isEmpty())
            return $this->notFound();

        return $this->response(['msg' => 'Payment Methods Retrieved Successfully','data' => $data->payment_methods],200);
    }

    public function createPaymentMethods(PaymentMethodsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $json = $request->json()->all();
        
        $merchant = Merchant::where('id',$merchantID);

        $result = collect($merchant->select('payment_methods')->first()->payment_methods);
        $counter = $result->max('id') ?? 0;
        $json['id'] = ++$counter;

        $merchant->update(['payment_methods' => $result->merge([$json])]);
        return $this->response(null,204);
    }

    public function deletePaymentMethods($id,PaymentMethodsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        
        $list = Merchant::where('id',$merchantID);
        $result = collect($list->select('payment_methods')->first()->payment_methods);

        $json = $result->reject(function ($value) use($id) {
            if($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['payment_methods' => collect($json)]);
        return $this->response(null,204);
    }
}
