<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Carbon\Carbon;
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
    protected $description = 'SH239 COD Balance';

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
    public function handle()
    {
        DB::transaction(function () {

            $shipments = DB::table('shipments')
                ->select('id', 'awb', 'merchant_id', 'cod', 'fees', 'created_by')
                ->whereRaw('date(paid_at) = date(now())')
                ->whereNull('transaction_id')
                ->where('is_collected', true)
                ->get();

            $merchant = 
            $shipments->map(function ($shipment) {
                $id = $shipment->id;
                $merchant_id = $shipment->merchant_id;
                $cod = $shipment->cod;
                $fees = $shipment->fees;
                $awb = $shipment->awb;
                $created_by = $shipment->created_by;

                $merchant = Merchant::findOrFail($merchant_id);
                if ($merchant->payment_type == 'POSTPAID') {
                    $amount = $cod - $fees;
                } else {
                    $amount = $cod;
                }

                $merchant->cod_balance += $amount;
                $merchant->save();

                $transaction_id = DB::table('transactions')->insertGetId(
                    [
                        'type' => 'CASHIN',
                        'subtype' => 'COD',
                        'item_id' => $awb,
                        'merchant_id' => $merchant_id,
                        'description' => 'Aramex SH239 webhook',
                        'balance_after' => $merchant->cod_balance,
                        'amount' => $amount,
                        'source' => 'SHIPMENT',
                        'status' => 'COMPLETED',
                        'created_by' => $created_by,
                        'resource' => 'API',
                        'payment_method' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]
                );
                DB::table('shipments')->where('id', $id)->update(['transaction_id' => $transaction_id]);
            });
        });
        return Command::SUCCESS;
    }
}
