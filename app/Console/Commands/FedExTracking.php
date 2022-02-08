<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\Merchant\ShipmentController;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Transaction;
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
            ->where('status', '<>', 'COMPLETED')
            ->get();

        $setup = [
            'DL' => ['status' => 'COMPLETED', 'delivered_at' => Carbon::now(), 'returned_at' => null, 'paid_at' => null],
            'OD' => ['status' => 'DRAFT'],
        ];

        $shipments->map(function ($shipment) use ($setup) {
            $trackDetails = $this->track('FedEx', $shipment->external_awb) ?? [];
            $event = $trackDetails['Events'];
            $last_update = $trackDetails['DatesOrTimes'][0]['Type'] ?? '';

            foreach ($trackDetails['DatesOrTimes'] as $key => $value) {
                $new[] = [
                    'UpdateDateTime' => Carbon::parse($value['DateOrTimestamp'])->format('Y-m-d H:i:s'),
                    'UpdateLocation' => 'N/A',
                    'UpdateDescription' => str_replace('_', ' ', $value['Type']),
                    'TrackingDescription' => 'N/A',
                ];
            }

            $updated = $setup[$event['EventType']] ?? ['status' => 'PROCESSING', 'actions' => ['check_chargable_weight']];
            $updated['shipping_logs'] = collect($new);
            $updated['last_update'] = str_replace('_', ' ', $last_update);

            if (isset($updated['actions'])) {
                $merchant = Merchant::findOrFail($shipment->merchant_id);
                if ($shipment->actual_weight < $trackDetails['ShipmentWeight']['Value']) {
                    $fees = (new ShipmentController)->calculateFees(
                        3,
                        null,
                        ($shipment->group == 'DOM') ? $shipment->consignee_city : $shipment->consignee_country,
                        $shipment->group,
                        $trackDetails['ShipmentWeight']['Value']
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
                }
                unset($updated['actions']);
            }
            $shipment->update($updated);
        });

        return Command::SUCCESS;
    }
}
