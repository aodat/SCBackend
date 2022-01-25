<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    public function handle()
    {
        $merchecntInfo = Merchant::findOrFail($this->mercanhtID);

        if ($merchecntInfo->bundle_balance <= 1) {
            return true;
        }

        $rounds = ceil($merchecntInfo->bundle_balance / 1000);
        $amounts = array_fill(0, $rounds - 1, 1000);
        $amounts[] = $merchecntInfo->bundle_balance - array_sum($amounts);

        $merchecntInfo->bundle_balance = 0;
        $merchecntInfo->save();
        
        $obj = new Dinarak();
        foreach ($amounts as $amount) {
            $result = $obj->deposit($this->payment['iban'], $amount);
        }

        $transaction = Transaction::findOrFail($this->transactionID);

        $result->update(['notes' => json_encode($result->json())]);

        $status = json_decode($result)->status->id;
        if ($status == 1) {
            $transaction->update(['status' => 'confirmed']);
        }

    }
}
