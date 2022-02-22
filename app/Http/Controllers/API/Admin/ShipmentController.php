<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShipmentRequest;
use App\Models\Shipment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShipmentController extends Controller
{

    private $status = [
        'DRAFT' => 0, 'PROCESSING' => 0, 'COMPLETED' => 0, 'RENTURND' => 0, 'PENDING_PAYMENTS' => 0,
    ];

    public function index(ShipmentRequest $request)
    {
        $filters = $request->json()->all();
        $merchant_id = $request->merchant_id;
        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $external = $filters['external'] ?? [];
        $statuses = $filters['statuses'] ?? [];
        $phone = $filters['phone'] ?? [];
        $cod = $filters['cod']['val'] ?? null;
        $operation = $filters['cod']['operation'] ?? null;
        $type = $request->type ?? 'DOM';

        $shipments = DB::table('shipments as s')->join('carriers as car', 'car.id', 's.carrier_id')
            ->where('merchant_id', $merchant_id)
            ->where('is_deleted', false)
            ->whereBetween('s.created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if (count($external)) {
            $shipments->whereIn('s.awb', $external);
        }

        if (count($phone)) {
            $shipments = $shipments->where(function ($query) use ($phone) {
                $query->whereIn('s.sender_phone', $phone)->orWhereIn('s.consignee_phone', $phone);
            });
        }

        if ($operation) {
            $shipments->where("s.cod", $operation, $cod);
        } else if ($cod) {
            $shipments->whereBetween('s.cod', [intval($cod), intval($cod) . '.99']);
        }

        $shipments->where('s.group', $type);
        if (count($statuses)) {
            if (in_array('PENDING_PAYMENTS', $statuses)) {
                $shipments->where('s.status', '=', 'COMPLETED')->whereNull('s.transaction_id');
            } else {
                $shipments->whereIn('s.status', $statuses);
            }

        }
        $shipments->orderBy('created_at', 'desc');
        $shipments->select(
            's.id',
            's.created_at',
            's.awb',
            's.consignee_name',
            's.consignee_email',
            's.consignee_phone',
            DB::raw('CASE WHEN s.status = \'COMPLETED\' and s.transaction_id is null THEN \'PENDING PAYMENTS\' ELSE s.status END as status'),
            's.status as actual_status',
            's.fees',
            's.url',
            's.consignee_country',
            's.consignee_city',
            's.consignee_area',
            'car.name as provider_name'
        );

        $tabs = DB::table(DB::raw("(select id,CASE WHEN s.status = 'COMPLETED' && s.transaction_id is null THEN 'PENDING_PAYMENTS' ELSE s.status END  as exstatus from shipments s where merchant_id = $merchant_id and `group` = '$type' and is_deleted = false) as subs"))
            ->select('exstatus', DB::raw('count(id) as counter'))
            ->groupByRaw('exstatus')
            ->pluck('counter', 'exstatus');

        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($shipments->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
    }

    public function show(ShipmentRequest $request)
    {
        $data = Shipment::where('merchant_id', $request->merchant_id)
            ->where('id', $request->shipment_id)
            ->first();

        if ($data === null) {
            return $this->error('Shipment ID Not Found', 400);
        }

        return $this->response($data, 'Data Retrieved Successfully');
    }

    public function update(ShipmentRequest $request)
    {
        $shipmentInfo = Shipment::findOrFail($request->shipment_id);
        if ($shipmentInfo->status == 'COMPLETED') {
            return $this->error('You Cant Update The Completed Shipment');
        }

        $shipmentInfo->cod = $request->amount;
        $shipmentInfo->admin_logs = collect($shipmentInfo->admin_logs)->merge([[
            'UpdateDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'UpdateLocation' => '',
            'UpdateDescription' => 'Update COD Value From ' . $shipmentInfo->cod . ' To ' . $request->amount,
        ]]);
        $shipmentInfo->save();

        Transaction::create(
            [
                'type' => 'CASHIN',
                'subtype' => 'COD',
                'item_id' => $shipmentInfo->id,
                'merchant_id' => $request->merchant_id,
                'source' => 'SHIPMENT',
                'status' => 'COMPLETED',
                'created_by' => Request()->user()->id,
                'balance_after' => $request->amount,
                'amount' => $request->amount,
                'resource' => 'API',
            ]
        );
        return $this->successful('COD Amount Updated');
    }
}
