<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
use App\Http\Controllers\Utilities\AWSServices;
use App\Http\Requests\Merchant\DocumentsRequest;
use Carbon\Carbon;

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
            'url' => AWSServices::uploadToS3('documents', $request->file('file')),
            'status' => 'pending',
            'verified_at' => null,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $merchant->update(['documents' => $result->merge([$data])]);
        return $this->successful('Created Successfully');
    }

    public function delete($id, DocumentsRequest $request)
    {
        $list = $this->getMerchentInfo();
        $result = collect($list->documents);
        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id && $value['verified_at'] == null) {
                return $value;
            } else if ($value['id'] == $id && $value['verified_at'] !== null) {
                throw new InternalException('You Can\'t Delete this Document', 400);
            }

        });

        $json = array_values($json->toArray());
        $list->update(['documents' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
