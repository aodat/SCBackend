<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\Utilities\Shipcash;
use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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
        $shipments = Shipment::where('carrier_id', 1)
            ->where(function ($where) {
                $where->orWhere('status', 'DRAFT')->orWhere('status', 'PROCESSING');
            })
            ->get();

        $shipments->map(function ($shipment) {
            $tracking = $this->track('Aramex', $shipment->external_awb, true) ?? [];
            if (!isset($tracking[0]['Value'])) {
                return $shipment;
            }
            $logs = $tracking[0]['Value'];
            $lastUpdateCode = $logs[0]['UpdateCode'];
            $lastUpdateTime = Shipcash::get_string_between($logs[0]['UpdateDateTime'], '/Date(', '+0200)/') / 1000;
            $chargable_weight = $logs[0]['ChargeableWeight'];

            $updated['last_update'] = $logs[0]['UpdateDescription'];
            $updated['shipping_logs'] = collect($logs);

            if ($shipment->chargable_weight < $chargable_weight) {
                $fees = (new ShipmentController)->calculateFees(
                    1,
                    null,
                    ($shipment->group == 'DOM') ? $shipment->consignee_city : $shipment->consignee_country,
                    $shipment->group,
                    $chargable_weight,
                    $shipment->merchant_id
                );

                $updated['fees'] = $fees;
                $updated['chargable_weight'] = $chargable_weight;
            }

            if (($lastUpdateCode == 'SH005' || $lastUpdateCode == 'SH006')) {
                if ($shipment->cod == 0) {
                    $request = Request::create('/api/aramex-webhook', 'POST', ['UpdateCode' => 'SH239', 'WaybillNumber' => $shipment->external_awb]);
                    Route::dispatch($request);
                } else {
                    // $updated['status'] = 'COMPLETED';
                    DB::table('shipments')->where('external_awb', $shipment->external_awb)->update(['status' => 'COMPLETED']);
                    $updated['delivered_at'] = Carbon::parse($lastUpdateTime)->format('Y-m-d H:i:s');
                }
            }

            if ($lastUpdateCode != 'SH014') {
                $updated['status'] = 'PROCESSING';
            }

            if ($lastUpdateCode == 'SH069') {
                $updated['status'] = 'RENTURND';
                $updated['returned_at'] = Carbon::parse($lastUpdateTime)->format('Y-m-d H:i:s');
            }

            $shipment->update($updated);
        });
        return Command::SUCCESS;
    }
}
