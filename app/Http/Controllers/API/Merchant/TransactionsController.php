<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\TransactionRequest;

use App\Models\Transaction;
use Carbon\Carbon;

class TransactionsController extends MerchantController
{
    public function index(TransactionRequest $request)
    {
        $filters = $request->json()->all();
 
        $since = $filters['created_at']['since'] ?? Carbon::today()->subDays(3)->format('Y-m-d');;
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $types = $filters['types'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $sources = $filters['sources'] ?? [];
        $amount = $filters['amount']['val'] ?? null;
        $operation = $filters['amount']['operation'] ?? null;
        
        $transaction = Transaction::whereBetween('created_at',[$since." 00:00:00",$until." 23:59:59"])
                        ->where('merchant_id',$request->user()->merchant_id);

        if(count($statuses))
            $transaction->whereIn('status',$statuses);

        if(count($sources))
            $transaction->whereIn('source',$sources);
                        
        if(count($types))
            $transaction->whereIn('type',$types);
        
        if($operation)
            $transaction->where('amount',$operation, $amount);
        else if($amount)
            $transaction->whereBetween('amount', [intval($amount), intval($amount).'.99']);
        $paginated = $transaction->paginate(request()->per_page ?? 10);
    
        return $this->response($paginated,'Data Retrieved Successfully',200,true);
    }

    public function show($id,TransactionRequest $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response($data,'Transaction Retrived Sucessfully',200);
    }
    
    public function withDraw(TransactionRequest $request)
    {
        $merchecntInfo = $this->getMerchentInfo();
        
        $actualBalance = $merchecntInfo->actual_balance;
        $paymentMethod = $merchecntInfo->payment_methods;
        $paymentMethodID = $request->payment_method_id;

        if($actualBalance < $request->amount)
            return $this->response([],'The Actual Balance Not Enough',500);


        $merchecntInfo->actual_balance = $actualBalance - $request->amount;
        $merchecntInfo->save();


        $selectedPayment = collect($paymentMethod)->reject(function ($value) use($paymentMethodID) {
            if($value['id'] != $paymentMethodID)
                return $value;
        });
        $selectedPayment = array_values($selectedPayment->toArray());

        Transaction::create(
            [
                'type' => 'CASHOUT',
                'merchant_id' => $request->user()->merchant_id,
                'source' => $request->source,
                'created_by' => $request->user()->id,
                'balance_after' => $request->amount,
                'payment_method' => collect($selectedPayment)
            ]
        );

        return $this->successful();
    }

    public function export(TransactionRequest $request)
    {

    }
}