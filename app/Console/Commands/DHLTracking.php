<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Traits\CarriersManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DHLTracking extends Command
{
    use CarriersManager;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-tracking:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DHL Tracking';

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
        $lists = DB::table('shipments')->where('carrier_id', 2)
            ->where('status', '<>', 'COMPLETED')
            ->pluck('external_awb');

        $lists->map(function ($external_awb) {
            $shipmentInfo = $this->track('DHL', $external_awb);
            $last_update = $shipmentInfo[0]['ServiceEvent']['Description'] ?? '';

            foreach ($shipmentInfo as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => $value['Date'] . ' ' . $value['Time'],
                    'UpdateLocation' => $value['ServiceArea']['Description'],
                    'UpdateDescription' => $value['ServiceEvent']['Description'],
                    'TrackingDescription' => 'N/A',
                ];

            }
            Shipment::withoutGlobalScope('ancient')
                ->where('external_awb', $external_awb)
                ->update([
                    'shipping_logs' => collect($new),
                    'last_update' => $last_update,
                ]);
        });
        return Command::SUCCESS;
    }
}
