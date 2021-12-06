<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentRequest;
use App\Models\Merchant;
use App\Exceptions\InternalException;
use Carbon\Carbon;

class DocumentController extends Controller
{
    public function index(DocumentRequest $request)
    {
        $data  = Merchant::find($request->merchant_id)->documents;
        return $this->response($data, "Data Retrieved Successfully");
    }

    public function store(DocumentRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        $result = collect($merchant->select('documents')->first()->documents);
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

    public function status(DocumentRequest $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? null;

        $merchant_id = $data['merchant_id'];

        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $documents = $merchant->documents;

        $documents = collect($documents);
        $document = $documents->where('id', $id);

        if ($document->first() == null)
            throw new InternalException('Document id not Exists');
        $current = $document->keys()->first();

        $data = $document->toArray()[$current];
        $data['verified_at'] =  Carbon::now();
        $data['status'] =  $request->status;
        $documents[$current] = $data;

        $merchant->update(['documents' => $documents]);
        return $this->successful("success Update");
    }
}
