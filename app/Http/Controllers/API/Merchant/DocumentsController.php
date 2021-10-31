<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DocumentsRequest;

use App\Models\Merchant;
use Carbon\Carbon;

class DocumentsController extends MerchantController
{

    public function index(DocumentsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('documents')->first();

      
        if(collect($data->documents)->isEmpty())
            return $this->notFound();

        return $this->response($data->documents,'Payment Methods Retrieved Successfully',200);
    }

    public function createDocuments(DocumentsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;        
        $merchant = Merchant::where('id',$merchantID);

        $result = collect($merchant->select('documents')->first()->documents);
        $counter = $result->max('id') ?? 0;
        
        $data = [
            'id' => ++$counter,
            'type' => $request->type,
            'url' => uploadFiles('documents',$request->file('file')),
            'created_at' => Carbon::now()
        ];

        $merchant->update(['documents' => $result->merge([$data])]);
        return $this->successful();
    }

    public function deleteDocuments($id,DocumentsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $list = Merchant::where('id',$merchantID);
        $result = collect($list->select('documents')->first()->documents);

        $json = $result->reject(function ($value) use($id) {
            if($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['documents' => collect($json)]);
        return $this->successful('Deleted Sucessfully');
    }
}
