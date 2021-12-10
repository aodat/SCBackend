<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\DocumentsRequest;

use App\Models\Merchant;
use Carbon\Carbon;
use App\Exceptions\InternalException;

class DocumentsController extends MerchantController
{

    public function index(DocumentsRequest $request)
    {
        $data = $this->getMerchentInfo();
        return $this->response($data->documents, 'Data Retrieved Successfully', 200);
    }

    public function store(DocumentsRequest $request)
    {
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->documents);
        $counter = $result->max('id') ?? 0;

        $data = [
            'id' => ++$counter,
            'type' => $request->type,
            'url' => uploadFiles('documents', $request->file('file')),
            'status' => 'pending',
            'verified_at' => null,
            'created_at' => Carbon::now()
        ];

        $merchant->update(['documents' => $result->merge([$data])]);
        return $this->successful('Create Successfully');
    }

    public function delete($id, DocumentsRequest $request)
    {
        $list = $this->getMerchentInfo();
        $result = collect($list->documents);
        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id && $value['verified_at'] == null)
                return $value;
        });
        $json = array_values($json->toArray());
        $list->update(['documents' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
