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


    public function create(DocumentRequest $request)
    {
        $template = json_decode(file_get_contents(storage_path() . '/app/template/documents.json'), true);

        $merchant_id = $request->merchant_id;
        $merchant = Merchant::where('id', '=', $merchant_id);

        $result = collect($merchant->select('documents')->first()->documents);
        $counter = $result->max('id') ?? 0;

        if ($request->hasFile('url'))
            $file_link = uploadFiles('documents', $request->file('url'));
        else
            throw new InternalException('file documents not Exists');

        $template['id']           = ++$counter;
        $template['type']         = $request->type;
        $template['url']          = $file_link;
        $template['status']       = "pending";
        $template['updated_at']   = Carbon::now();
        $template['created_at']   = Carbon::now();

        $merchant->update(['documents' => $result->merge([$template])]);
        return $this->successful();
    }



    public function verifiedDocument(DocumentRequest $request)
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
        $data['updated_at'] =  Carbon::now();
        $data['status'] =  "verified";
        $documents[$current] = $data;

        $merchant->update(['documents' => $documents]);
        return $this->successful("success verified");
    }
}
