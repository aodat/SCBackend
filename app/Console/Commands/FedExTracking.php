<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Traits\CarriersManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $shipments = Shipment::where('carrier_id', 3)
            ->where(function ($where) {
                $where->orWhere('status', '<>', 'COMPLETED')->orWhere('status', '<>', 'RENTURND');
            })
            ->get();

        $setup = [
            'DL' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null],
            'OD' => ['status' => 'DRAFT'],
        ];

        $shipments->map(function ($shipment) use ($setup) {
            $trackDetails = $this->track('FedEx', $shipment->external_awb) ?? [];
            $event = $trackDetails['Events'] ?? [];

            if (empty($event)) {
                return $shipment;
            }

            $last_update = $trackDetails['DatesOrTimes'][0]['Type'] ?? '';

            foreach ($trackDetails['DatesOrTimes'] as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => Carbon::parse($value['DateOrTimestamp'])->format('Y-m-d H:i:s'),
                    'UpdateLocation' => 'N/A',
                    'UpdateDescription' => str_replace('_', ' ', $value['Type']),
                ];
            }

            $updated = $setup[$event['EventType']] ?? ['status' => 'PROCESSING', 'actions' => ['check_chargable_weight']];
            $updated['shipping_logs'] = collect($new);
            $updated['last_update'] = str_replace('_', ' ', $last_update);

            if (isset($updated['actions'])) {
                $merchant = Merchant::findOrFail($shipment->merchant_id);
                if ($shipment->chargable_weight != $trackDetails['ShipmentWeight']['Value']) {
                    $fees = (new ShipmentController)->calculateFees(
                        3,
                        null,
                        $shipment->consignee_country,
                        $shipment->group,
                        $trackDetails['ShipmentWeight']['Value']
                    );

                    // Check the paid fees in this shipment
                    // $diff = $fees - $shipment->fees;
                    // $merchant->bundle_balance -= $diff;
                    // $merchant->save();

                    $updated['fees'] = $fees;
                    $updated['chargable_weight'] = $trackDetails['ShipmentWeight']['Value'];

                    $logs = collect($shipment->admin_logs);

                    $updated['admin_logs'] = $logs->merge([[
                        'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'UpdateLocation' => '',
                        'UpdateDescription' => 'Update Shipment Weight From ' . $shipment->actual_weight . ' To ' . $trackDetails['ShipmentWeight']['Value'],

                    ]]);

                }
                unset($updated['actions']);
            }
            $shipment->update($updated);
        });

        return Command::SUCCESS;
    }
}
