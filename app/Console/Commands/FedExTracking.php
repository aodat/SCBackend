<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FedExTracking extends Command
{
    use CarriersManager;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fedex-tracking:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'FedEx Tracking';

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
        $lists = DB::table('shipments')->where('carrier_id', 3)
            ->where('status', '<>', 'COMPLETED')
            ->pluck('external_awb');

        $lists->map(function ($external_awb) {
            $shipmentInfo = $this->track('FedEx', $external_awb)['DatesOrTimes'] ?? [];
            $last_update = $shipmentInfo[0]['Type'] ?? '';

            foreach ($shipmentInfo as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => Carbon::parse($value['DateOrTimestamp'])->format('Y-m-d H:i:s'),
                    'UpdateLocation' => 'N/A',
                    'UpdateDescription' => str_replace('_', ' ', $value['Type']),
                    'TrackingDescription' => 'N/A',
                ];

            }
            Shipment::withoutGlobalScope('ancient')
                ->where('external_awb', $external_awb)
                ->update([
                    'shipping_logs' => collect($new),
                    'last_update' => str_replace('_', ' ', $last_update),
                ]);
        });
        return Command::SUCCESS;
    }
}
