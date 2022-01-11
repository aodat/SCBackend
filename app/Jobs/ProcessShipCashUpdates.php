<?php

namespace App\Jobs;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Traits\CarriersManager;

class ProcessShipCashUpdates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CarriersManager;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data;
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shipmentInfo = Shipment::withoutGlobalScope('ancient')->where('external_awb', $this->data['WaybillNumber'])->first();
        $this->webhook($shipmentInfo, $this->data);

        return true;
    }
}
