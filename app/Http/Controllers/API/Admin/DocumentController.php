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
        return $this->response($data, "Success get addresse to specified merchant");
    }

    public function update(DocumentRequest $request)
    {
        $data = $request->validated();

        $data = $request->all();
        $id = $data['id'] ?? null;
        $data["status"] = "pending";
        $data['updated_at'] = Carbon::now();
        $merchant_id = $data['merchant_id'];

        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $documents = $merchant->documents;

        $documents = collect($documents);
        $document = $documents->where('id', $id);

        if ($document->first() == null)
            throw new InternalException('Document id not Exists');

        $data['url']  = uploadFiles('documents', $request->file('url'));

        $current = $document->keys()->first();
        $documents[$current] = $data;
        $merchant->update(['documents' => $documents]);
        return $this->successful('Updated Sucessfully');
    }
}
