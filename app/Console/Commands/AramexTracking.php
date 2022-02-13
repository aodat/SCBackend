<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Http\Controllers\Utilities\Shipcash;
use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
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
                $where->orWhere('status', '<>', 'COMPLETED')->orWhere('status', '<>', 'RENTURND');
            })
            ->get();
        $setup = [
            'SH014' => ['status' => 'DRAFT', 'delivered_at' => null, 'returned_at' => null, 'paid_at' => null],
            'SH005' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null],
            'SH006' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null],
            'SH069' => ['status' => 'RENTURND', 'returned_at' => Carbon::now(), 'delivered_at' => null, 'paid_at' => null],
        ];

        $shipments->map(function ($shipment) use ($setup) {

            $track = $this->track('Aramex', $shipment->external_awb, true) ?? [];
            if (!isset($track[0]['Value'])) {
                return $shipment;
            }

            $events = $track[0]['Value'];

            $lastEvent = $events[0]['UpdateCode'] ?? [];
            $ChargeableWeight = $events[0]['ChargeableWeight'];

            $updated = $setup[$lastEvent] ?? ['status' => 'PROCESSING', 'actions' => ['check_chargable_weight']];

            $updated['last_update'] = $events[0]['UpdateDescription'] ?? null;
            $new = [];
            foreach ($events as $key => $value) {
                $time = Shipcash::get_string_between($value['UpdateDateTime'], '/Date(', '+0200)/') / 1000;
                $new[] = [
                    'UpdateDateTime' => Carbon::parse($time)->format('Y-m-d H:i:s'),
                    'UpdateLocation' => $value['UpdateLocation'],
                    'UpdateDescription' => $value['UpdateDescription'],
                ];
            }

            $updated['shipping_logs'] = collect($new);
            if (isset($updated['actions'])) {
                if ($shipment->chargable_weight < $ChargeableWeight) {
                    $fees = (new ShipmentController)->calculateFees(
                        1,
                        null,
                        ($shipment->group == 'DOM') ? $shipment->consignee_city : $shipment->consignee_country,
                        $shipment->group,
                        $ChargeableWeight,
                        $shipment->merchant_id
                    );

                    $updated['fees'] = $fees;
                    $updated['chargable_weight'] = $ChargeableWeight;

                    $logs = collect($shipment->admin_logs);

                    $updated['admin_logs'] = $logs->merge([[
                        'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'UpdateLocation' => '',
                        'UpdateDescription' => 'Update Shipment Weight From ' . $shipment->actual_weight . ' To ' . $ChargeableWeight,

                    ]]);

                }
                unset($updated['actions']);
            }
            $shipment->update($updated);

            if (($lastEvent == 'SH006' || $lastEvent == 'SH006') && ($shipment->cod == 0)) {
                $request = Request::create('/api/aramex-webhook', 'POST', ['UpdateCode' => 'SH239', 'WaybillNumber' => $shipment->external_awb]);
                Route::dispatch($request);
            }
        });
        return Command::SUCCESS;
    }
}
