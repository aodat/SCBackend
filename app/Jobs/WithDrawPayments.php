<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Libs\Dinarak;

class WithDrawPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    private $mercanhtID, $amount, $payment, $transactionID;
    public function __construct($mercanhtID, $amount, $payment, $transactionID)
    {
        $this->mercanhtID = $mercanhtID;
        $this->amount = $amount;
        $this->payment = $payment;
        $this->transactionID = $transactionID;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Dinarak $dinarak)
    {
        DB::transaction(function () use ($dinarak) {
            $merchecntInfo = Merchant::findOrFail($this->mercanhtID);

            if ($merchecntInfo->cod_balance <= 0 || $this->amount > $merchecntInfo->cod_balance) {
                return true;
            }
            
            $dinarak->withdraw($merchecntInfo, $$this->payment['iban'], $this->amount);

            $merchecntInfo->cod_balance -= $merchecntInfo->amount;
            $merchecntInfo->save();

            $transaction = Transaction::findOrFail($this->transactionID);

            // $transaction->notes = collect($result);
            $transaction->status = 'COMPLETED';
            $transaction->save();
        });
    }
}
