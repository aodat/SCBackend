<?php

namespace App\Http\Controllers\API\Transactions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transaction;
class TransactionsController extends Controller
{
    public function getAllTransactions(Request $request)
    {

    }

    public function getTransaction($id,Request $request)
    {
        $data = Transaction::findOrFail($id);
        return $this->response(['msg' => 'Transaction Retrived Sucessfully','data' => $data],200);
    }
    
    public function withDraw(Request $request)
    {

    }

    public function export(Request $request)
    {

    }
}