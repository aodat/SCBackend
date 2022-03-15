<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\TransactionsController;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CODTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cod-transactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(TransactionsController $transaction)
    {
        $shipments = DB::table('shipments')
            ->select('id', 'awb', 'merchant_id', 'cod', 'fees', 'created_by')
            ->whereRaw('date(paid_at) = date(now())')
            ->whereNull('transaction_id')
            ->where('is_collected', true)
            ->get();

        $shipments->map(function ($shipment) use ($transaction) {
            $id = $shipment->id;
            $merchant_id = $shipment->merchant_id;

            $merchant = Merchant::findOrFail($merchant_id);

            $cod = $shipment->cod;
            $fees = $shipment->fees;
            $awb = $shipment->awb;
            $created_by = $shipment->created_by;


            if ($merchant->payment_type == 'POSTPAID') {
                $amount = $cod - $fees;
            } else {
                $amount = $cod;
            }

            $transaction_id = $transaction->COD(
                'CASHIN',
                $merchant_id,
                $awb,
                $amount,
                "SHIPMENT",
                $created_by,
                'Aramex SH239 webhook',
                'COMPLETED',
                'API'
            );

            DB::table('shipments')->where('id', $id)->update(['transaction_id' => $transaction_id]);
        });
        return Command::SUCCESS;
    }
}
