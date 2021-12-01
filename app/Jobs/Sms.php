<?php

namespace App\Jobs;

use App\Http\Controllers\Utilities\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Sms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $phone;
    protected $randomPinCode;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($randomPinCode, $phone)
    {
        //
        $this->phone = $phone;
        $this->randomPinCode = $randomPinCode;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        SmsService::sendSMS($this->randomPinCode, $this->phone);
    }
}
