<?php

namespace App\Http\Controllers\API\Merchant;

use App\Models\Merchant;

use App\Http\Requests\Merchant\SendersRequest;

class SendersController extends MerchantController
{
    public function index(SendersRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('senders')->first();

        
        if(collect($data->senders)->isEmpty())
            return $this->notFound();

        return $this->response(['msg' => 'Payment Methods Retrieved Successfully','data' => $data->senders],200);
    }

    public function createSenders(SendersRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $json = $request->json()->all();
        
        $merchant = Merchant::where('id',$merchantID);

        $result = collect($merchant->select('senders')->first()->senders);
        $counter = $result->max('id') ?? 0;
        $json['id'] = ++$counter;

        $merchant->update(['senders' => $result->merge([$json])]);
        return $this->successful(null,204);
    }

    public function deleteSenders($id,SendersRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        
        $list = Merchant::where('id',$merchantID);
        $result = collect($list->select('senders')->first()->senders);

        $json = $result->reject(function ($value) use($id) {
            if($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['senders' => collect($json)]);
        return $this->successful(null,204);
    }
    
}
