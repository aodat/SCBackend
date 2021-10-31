<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\InvoiceRequest;

use App\Models\Invoices;
class InvoiceController extends MerchantController
{
    public function store(InvoiceRequest $request)
    {
        $data = $request->validated();
    
        // on create invoice you can build it by stripe
        $receipt = $this->invoice($data); 
        $data['fk_id'] = $receipt['fk_id'];
        $data['merchant_id'] = $request->user()->merchant_id;
        $data['user_id'] = $request->user()->id;
        Invoices::create($data);

        return $this->successful(); 
    }

    
    public function delete($invoiceID,InvoiceRequest $request)
    {
        $invoiceInfo = Invoices::where('id',$invoiceID)->where('merchant_id',$request->user()->merchant_id)->first();
        if($invoiceInfo->status != 'DRAFT')
            return $this->error('you cant delete this invoice');
        $this->deleteInvoice($invoiceInfo->fk_id);    
        $invoiceInfo->delete();


        return $this->successful('Deleted Sucessfully'); 
    }
}
