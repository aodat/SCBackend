<?php

namespace App\Console\Commands;

use App\Models\Pickup;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MerchantCronJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel Pickup';

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
        Pickup::whereDate('pickup_date', '<=', Carbon::today())->update(['status' => 'DONE']);
        Pickup::whereNotNull('cancel_ref')->update(['status' => 'CANCELD']);
        return Command::SUCCESS;
    }
}
