<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Merchant;

use App\Http\Requests\Merchant\PaymentMethodsRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class PaymentMethodsController extends MerchantController
{
    public function index(PaymentMethodsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('payment_methods')->first();
      
        if(collect($data->payment_methods)->isEmpty())
            return $this->notFound();

        return $this->response($data->payment_methods,'Data Retrieved Successfully',200);
    }

    public function createPaymentMethods(PaymentMethodsRequest $request)
    {
        $json = $request->json()->all();
        $list = $this->getMerchentInfo();
        $result = collect($list->payment_methods);
        $counter = $result->max('id') ?? 0;

        // Get Merchants Template 
        $paymentMthodsTemplate = collect(json_decode(Storage::disk('local')->get('template/payment_methods.json'),true));
        $json += $paymentMthodsTemplate->where('provider_code',strtolower($json['provider_code']))->first();
        $json['id'] = ++$counter;
        $json['created_at'] = Carbon::now();
        if(isset($json['pin_code']))
            unset($json['pin_code']);

        $list->update(['payment_methods' => $result->merge([$json])]);
        return $this->successful();
    }

    public function deletePaymentMethods($id,PaymentMethodsRequest $request)
    {        
        $list = $this->getMerchentInfo();
        $result = collect($list->select('payment_methods')->first()->payment_methods);

        $json = $result->reject(function ($value) use($id) {
            if($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['payment_methods' => collect($json)]);
        return $this->successful('Deleted Sucessfully');
    }
}
