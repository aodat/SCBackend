<?php

namespace App\Http\Controllers\API\Admin;

use App\Exports\TransactionsExportAdmin;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\Documents;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;

class TransactionsController extends Controller
{
    public function export(Request $request)
    {
        $transactions = Transaction::where('subtype', 'COD')->where('type', 'CASHOUT')->where('status','PROCESSING')->get();

        $path = "export/transaction-cod-" . Carbon::today()->format('Y-m-d') . ".xlsx";

        $url = Documents::xlsx(new TransactionsExportAdmin($transactions), $path);
        return $this->response(['link' => $url], 'Data Retrieved Sucessfully', 200);
    }
}
