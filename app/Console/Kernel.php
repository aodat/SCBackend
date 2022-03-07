<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\AramexTracking::class,
        Commands\DHLTracking::class,
        Commands\FedExTracking::class,
        Commands\PickUpTracking::class,
        Commands\CODTransactions::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('pickup-tracking:cron')->daily();
        $schedule->command('aramex-tracking:cron')->everyFiveMinutes();
        $schedule->command('dhl-tracking:cron')->everyFiveMinutes();
        $schedule->command('fedex-tracking:cron')->everyFiveMinutes();
        $schedule->command('cod-transactions:cron')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
