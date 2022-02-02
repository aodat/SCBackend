<?php

namespace App\Console\Commands;

use App\Jobs\AramexTracking as JobsAramexTracking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AramexTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aramex-tracking:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aramex Tracking';

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
        $lists = DB::table('shipments')->where('carrier_id', 1)
            ->where('status', 'PROCESSING')
            ->pluck('external_awb');
        $lists->map(function ($external_awb) {
            JobsAramexTracking::dispatch($external_awb);
        });
        return true;
    }
}
