<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\DocumentRequest;
use App\Models\Merchant;
use App\Exceptions\InternalException;
use Carbon\Carbon;

class DocumentController extends Controller
{
    //
    public function index(DocumentRequest $request)
    {

        $data  = Merchant::find($request->merchant_id);
        if ($data !== null)
            return $this->response($data->documents, "Success get documents to specified merchant");
        else
            throw new InternalException('documents id not Exists');
    }
    public function update(DocumentRequest $request)
    {
        $data = $request->validated();

        $data = $request->all();
        $id = $data['id'] ?? null;
        $data["status"]="pending";
        $data['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
        $merchant_id = $data['merchant_id'];

        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $documents = $merchant->documents;

        $documents = collect($documents);
        $document = $documents->where('id', $id);

        if ($document->first() == null)
            throw new InternalException('addresse id not Exists');

        if ($request->hasFile('url'))
            $data['url']  = uploadFiles2('documents', $request->file('url'));
        else {
            throw new InternalException(' ');
            return $this->response($data, "Make sure it's a file");
        }
        

        $current = $document->keys()->first();
        $documents[$current] = $data;
        $merchant->update(['documents' => $documents]);
        return $this->successful('Updated Sucessfully');
    }
}
