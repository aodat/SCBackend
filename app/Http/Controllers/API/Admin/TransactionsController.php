<?php

namespace App\Http\Controllers\API\Admin;

use App\Exports\TransactionsExportAdmin;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\Documents;
use App\Http\Requests\Admin\TransactionRequest;
use App\Models\Merchant;
use App\Models\Transaction;
use Carbon\Carbon;
use Libs\Dinarak;

class TransactionsController extends Controller
{
    public function index(TransactionRequest $request)
    {
        $transactions = Transaction::where('subtype', 'COD')
            ->where('type', 'CASHOUT')
            ->where('status', 'PROCESSING');
        return $this->pagination($transactions->paginate(request()->per_page ?? 30));
    }

    public function export(TransactionRequest $request)
    {
        $transactions = Transaction::where('subtype', 'COD')->where('type', 'CASHOUT')->where('status', 'PROCESSING')->get();

        $path = "export/transaction-cod-" . Carbon::today()->format('Y-m-d') . ".xlsx";

        $url = Documents::xlsx(new TransactionsExportAdmin($transactions), $path);
        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }

    public function withdraw(TransactionRequest $request, Dinarak $dinark)
    {
        $transaction = Transaction::findOrFail($request->id);
        $merchecntInfo = Merchant::findOrFail($transaction->merchant_id);

        if ($transaction->status == 'COMPLETED') {
            return $this->error('This transaction was paid');
        }

        $dedaction = $transaction->amount - 0.35;

        // This Tranasction for testing only
        $result = '{"id":898353,"name":"Tareq Fawakhiri","receiverID":"962772170353","amount":1,"description":"Transfer from ShipCash","creationDate":"2022-02-17T08:52:13.6572354Z","approvalDate":"2022-02-17T08:52:13.6831651Z","reference":"83549122033917620e0c349480d422354571","financialReference":"266695128792580","status":{"id":1,"name":"Success","errorMessage":null}}';
        if (env('APP_ENV') == 'production') {
            $result = $dinark->withdraw($merchecntInfo, $transaction->payment_method['iban'], $dedaction);
        }
        $status = json_decode($result)->status->id ?? null;

        if ($status == 1 || $status == 2) {
            $transaction->status = 'COMPLETED';
        }

        if (env('APP_ENV') == 'production') {
            $transaction->notes = json_encode($result->json());
        } else {
            $transaction->notes = json_encode(json_decode($result));
        }

        $transaction->save();

        return $this->successful('Tansaction Was ' . json_decode($result)->status->name);
    }
}
