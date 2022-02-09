<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
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
            $trackDetails = $this->track('DHL', $shipment->external_awb) ?? [];

            $events = array_reverse($trackDetails['ShipmentEvent'] ?? []);

            $lastEvent = $events[0]['ServiceEvent']['EventCode'] ?? [];
            $last_update = $events[0]['ServiceEvent']['Description'] ?? null;

            $ShipmentEvent = array_reverse($trackDetails['ShipmentEvent']);
            foreach ($ShipmentEvent as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => $value['Date'] . ' ' . $value['Time'],
                    'UpdateLocation' => $value['ServiceArea']['Description'],
                    'UpdateDescription' => $value['ServiceEvent']['Description'],
                    'TrackingDescription' => 'N/A',
                ];
            }

            $updated = $setup[$lastEvent] ?? ['status' => 'PROCESSING', 'actions' => ['check_chargable_weight']];
            $updated['shipping_logs'] = collect($new);
            $updated['last_update'] = str_replace('_', ' ', $last_update);

            if (isset($updated['actions'])) {
                $merchant = Merchant::findOrFail($shipment->merchant_id);
                if ($shipment->chargable_weight != $trackDetails['Weight']) {
                    $fees = (new ShipmentController)->calculateFees(
                        3,
                        null,
                        ($shipment->group == 'DOM') ? $shipment->consignee_city : $shipment->consignee_country,
                        $shipment->group,
                        $trackDetails['Weight']
                    );

                    // Check the paid fees in this shipment
                    $diff = $fees - $shipment->fees;
                    $merchant->bundle_balance -= $diff;
                    $merchant->save();

                    Transaction::create(
                        [
                            'type' => 'CASHOUT',
                            'subtype' => 'BUNDLE',
                            'item_id' => $shipment->id,
                            'merchant_id' => $shipment->merchant_id,
                            'source' => 'SHIPMENT',
                            'status' => 'COMPLETED',
                            'created_by' => $shipment->created_by,
                            'balance_after' => $merchant->bundle_balance,
                            'amount' => $diff,
                            'resource' => 'API',
                        ]
                    );
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
