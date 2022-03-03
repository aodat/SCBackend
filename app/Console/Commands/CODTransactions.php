<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\Shipment;
use Illuminate\Console\Command;
use App\Http\Controllers\API\Merchant\TransactionsController;

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
    protected $description = 'Create Tranasctions For Completed Shipments / SH239';

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
        $shipments = Shipment::where('carrier_id', 1)
            ->where('is_collected', true)
            ->where('status', 'COMPLETED')
            ->whereNull('transaction_id')
            ->get();
            
        $shipments->map(function ($shipmentInfo) use ($transaction) {

            $cod         = $shipmentInfo['cod'];
            $merchant_id = $shipmentInfo['merchant_id'];
            $fees        = $shipmentInfo['fees'];

            $merchant = Merchant::findOrFail($merchant_id);


            if ($merchant->payment_type == 'POSTPAID') {
                $amount = $cod - $fees;
            } else {
                $amount = $cod;
            }

            $awb = $shipmentInfo['awb'];
            $created_by = $shipmentInfo['created_by'];

            $transaction_id = $transaction->COD(
                'CASHIN',
                $merchant_id,
                $awb,
                $amount,
                "SHIPMENT",
                $created_by,
                'Aramex SH239 Tracking',
                'COMPLETED',
                'API'
            );

            $shipmentInfo->update(['transaction_id' => $transaction_id]);
        });
        return Command::SUCCESS;
    }
}
