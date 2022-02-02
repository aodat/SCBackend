<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AramexTracking extends Command
{
    use CarriersManager;
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
            $result = collect($this->track('Aramex', [$external_awb], true));
            $result->map(function ($info) use ($external_awb) {
                $shipmentInfo = $info['Value'];

                $new = [];
                $last_update = $shipmentInfo[0]['Comments'] ?? '';

                foreach ($shipmentInfo as $key => $value) {
                    $time = get_string_between($value['UpdateDateTime'], '/Date(', '+0200)/') / 1000;
                    $new[] = [
                        'UpdateDateTime' => Carbon::parse($time)->format('Y-m-d H:i:s'),
                        'UpdateLocation' => $value['UpdateLocation'],
                        'UpdateDescription' => $value['Comments'],
                        'TrackingDescription' => $value['UpdateDescription'],
                    ];
                }

                Shipment::withoutGlobalScope('ancient')
                    ->where('external_awb', $external_awb)
                    ->update([
                        'shipping_logs' => collect($new),
                        'last_update' => $last_update,
                    ]);
            });
        });
        return Command::SUCCESS;
    }
}
