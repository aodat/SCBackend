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
use Illuminate\Support\Facades\Log;

class AramexTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CarriersManager;

    protected $external_awb;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($external_awb)
    {
        $this->external_awb = $external_awb;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $result = collect($this->track('Aramex', [$this->external_awb], true));
        $result->map(function ($info) {
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
            Shipment::withoutGlobalScope('ancient')->where('external_awb', $this->external_awb)->update(['shipment_logs' => collect($new)]);
        });

        return true;
    }
}
