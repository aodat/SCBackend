<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AramexTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CarriersManager;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::transaction(function () {
            $lists = DB::table('shipments')->where('carrier_id', 1)
                ->where('status', 'PROCESSING')
                ->pluck('external_awb');
            $result = collect($this->track('Aramex', $lists->toArray()));
            $result->map(function ($info) {
                $shipmentID = $info['Key'];
                $shipmentInfo = $info['Value'];

                $new = [];
                foreach ($shipmentInfo as $key => $value) {
                    $time = get_string_between($value['UpdateDateTime'], '/Date(', '+0200)/') / 1000;
                    $new[] = [
                        'UpdateDateTime' => Carbon::parse($time)->format('Y-m-d H:i:s'),
                        'UpdateLocation' => $value['UpdateLocation'],
                        'UpdateDescription' => $value['Comments'],
                        'TrackingDescription' => $value['UpdateDescription'],
                    ];
                }

                shipment::withoutGlobalScope('ancient')->where('external_awb', $shipmentID)->update(['shipment_logs' => collect($new)]);
            });
        });

        return true;
    }
}
