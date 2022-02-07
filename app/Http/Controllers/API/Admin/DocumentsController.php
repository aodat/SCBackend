<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\AWSServices;
use App\Http\Requests\Admin\DocumentsRequest;
use App\Models\Merchant;
use Carbon\Carbon;

class DocumentsController extends Controller
{
    private $merchant;
    public function __construct(DocumentsRequest $request)
    {
        $this->merchant = Merchant::findOrFail($request->merchant_id);
    }

    public function index(DocumentsRequest $request)
    {
        return $this->response($this->merchant->documents, "Data Retrieved Successfully");
    }

    public function store(DocumentsRequest $request)
    {
        $merchant = $this->merchant;
        $result = collect($this->merchant->documents);

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

    public function status(DocumentsRequest $request)
    {
        $merchant = $this->merchant;
        $result = collect($this->merchant->documents);

        if ($result->where('id', $request->id)->count() == 0) {
            throw new InternalException('Document id not Exists');
        }

        $document = $result->where('id', $request->id);

        $current = $document->keys()->first();

        $data = $document->toArray()[$current];
        $data['verified_at'] = Carbon::now()->format('Y-m-d H:i:s');
        $data['status'] = $request->status;
        $result[$current] = $data;

        $merchant->update(['documents' => $result]);
        return $this->successful("Successfully Update");
    }
}
