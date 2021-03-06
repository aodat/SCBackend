<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $shipments = Shipment::where('carrier_id', 2)
            ->where(function ($where) {
                $where->orWhere('status', '<>', 'COMPLETED')->orWhere('status', '<>', 'RENTURND');
            })
            ->get();
        $setup = [
            'OK' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null],
            'PU' => ['status' => 'DRAFT'],
        ];

        $shipments->map(function ($shipment) use ($setup) {
            $trackDetails = $this->track('DHL', $shipment->awb) ?? [];

            $events = array_reverse($trackDetails['ShipmentEvent'] ?? []);

            $lastEvent = $events[0]['ServiceEvent']['EventCode'] ?? null;
            $last_update = $events[0]['ServiceEvent']['Description'] ?? null;

            $ShipmentEvent = array_reverse($trackDetails['ShipmentEvent'] ?? []);
            $new = [];
            foreach ($ShipmentEvent as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => $value['Date'] . ' ' . $value['Time'],
                    'UpdateLocation' => $value['ServiceArea']['Description'],
                    'UpdateDescription' => $value['ServiceEvent']['Description'],
                ];
            }
            $updated = $setup[$lastEvent] ?? ['status' => 'PROCESSING', 'actions' => ['check_chargable_weight']];
            $updated['shipping_logs'] = collect($new);
            $updated['last_update'] = str_replace('_', ' ', $last_update);

            if (isset($updated['actions'])) {
                $merchant = Merchant::findOrFail($shipment->merchant_id);
                if (floatval($shipment->chargable_weight) < floatval($trackDetails['Weight'])) {

                    $fees = (new ShipmentController)->calculateExpressFees(
                        2,
                        $shipment->consignee_country,
                        $trackDetails['Weight'],
                        $shipment->merchant_id
                    );

                    // Check the paid fees in this shipment
                    // $diff = $fees - $shipment->fees;
                    // $merchant->bundle_balance -= $diff;
                    // $merchant->save();

                    $updated['fees'] = $fees;
                    $updated['chargable_weight'] = $trackDetails['Weight'];

                    $logs = collect($shipment->admin_logs);

                    $updated['admin_logs'] = $logs->merge([[
                        'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'UpdateLocation' => '',
                        'UpdateDescription' => 'Update Shipment Weight From ' . $shipment->actual_weight . ' To ' . $trackDetails['Weight'],

                    ]]);

                }
                unset($updated['actions']);
            }
            $shipment->update($updated);

        });
        return Command::SUCCESS;
    }
}
