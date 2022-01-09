<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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

    public $payment_info, $amount, $transaction;
    public function __construct($payment_info, $amount, Transaction $transaction)
    {
        $this->payment_info = $payment_info;
        $this->amount = $amount;
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->payment_info['code'] == 'dn') {
            $obj = new Dinarak();
            $obj->deposit($this->payment_info['iban'], $this->amount, $this->transaction);
        }
    }
}
