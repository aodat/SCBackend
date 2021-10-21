<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DocumentsRequest;
use App\Http\Controllers\Utilities\uploadController;

use App\Models\Merchant;

class DocumentsController extends MerchantController
{

    public function index(DocumentsRequest $request)
    {
        $merchantID = $request->user()->merchant_id;
        $data = Merchant::where('id',$merchantID)->select('documents')->first();

      
        if(collect($data->documents)->isEmpty())
            return $this->notFound();

        return $this->response(['msg' => 'Payment Methods Retrieved Successfully','data' => $data->documents],200);
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
            'url' => uploadController::uploadFiles('documents',$request->file('file'))
        ];

        $merchant->update(['documents' => $result->merge([$data])]);
        return $this->response(null,204);
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
        return $this->response(null,204);
    }
}
