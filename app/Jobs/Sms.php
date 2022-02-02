<?php

namespace App\Jobs;

use App\Http\Controllers\Utilities\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Sms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $phone, $code;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $phone)
    {
        //
        $this->phone = $phone;
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        SmsService::sendSMS($this->code, $this->phone);
    }
}
