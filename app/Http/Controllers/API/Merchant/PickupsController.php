<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use App\Models\Carriers;
use App\Models\Pickup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PickupsController extends MerchantController
{
    private $status = [
        'DONE' => 0, 'PROCESSING' => 0, 'CANCELD' => 0,
    ];

    public function index(PickuptRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subYear(1)->format('Y-m-d');
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $pickupID = $request->pickup_id ?? null;
        $carrierID = $request->carrier_id ?? null;
        $status = $request->status ?? null;

        $pickup = Pickup::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"]);
        if ($pickupID != null) {
            $pickup->where('id', $pickupID);
        }

        if ($status != null) {
            $pickup->whereIn('status', $status);
        }

        if ($carrierID != null) {
            $pickup->where('carrier_id', $carrierID);
        }

        $tabs = DB::table('pickups')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->groupBy('status')
            ->pluck('counter', 'status');

        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($pickup->paginate(request()->per_page ?? 30), ['tabs' => $tabs]);
    }

    public function show($id, PickuptRequest $request)
    {
        $data = Pickup::findOrFail($id);
        return $this->response($data, 'Data Retrieved Sucessfully');
    }

    public function store(PickuptRequest $request)
    {
        $data = $request->validated();

        $ready_time = Carbon::createFromFormat('Y-m-d H:i', $data['pickup_date'] . ' ' . $data['from']);
        $closing_time = Carbon::createFromFormat('Y-m-d H:i', $data['pickup_date'] . ' ' . $data['to']);

        $at_4 = Carbon::createFromFormat('Y-m-d H:i', $data['pickup_date'] . ' ' . '16:00');
        $after_2_hour = Carbon::now()->addHours(2);

        if ($ready_time->lessThan($after_2_hour)) {
            return $this->error('From time should be at least after 2 hours now.', 422);
        } else if ($closing_time->diffInMinutes($ready_time) < 90) {
            return $this->error('The difference between From and To should be at least 90 minutes.', 422);
        } else if ($closing_time->lessThan($ready_time)) {
            return $this->error('From time is earlier than To time!', 422);
        } else if ($closing_time->greaterThan($at_4)) {
            return $this->error('The maximum time should be earlier than 4:00 PM', 422);
        }

        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);
        $address = $merchentAddresses->where('id', '=', $data['address_id'])->first();
        if ($address == null) {
            return $this->error('address id is not valid', 400);
        }
        $provider = Carriers::findOrfail($data['carrier_id'])->name;

        $pickupInfo = $this->generatePickup($provider, ['date' => $data['pickup_date'], 'ready' => $ready_time, 'close' => $closing_time], $address);
        $data['merchant_id'] = $merchentInfo->id;
        $data['hash'] = $pickupInfo['guid'];
        $data['cancel_ref'] = $pickupInfo['id'];

        Pickup::updateOrCreate(
            ['merchant_id' => $merchentInfo->id, 'hash' => $pickupInfo['guid'], 'carrier_id' => $data['carrier_id']],
            ['merchant_id' => $merchentInfo->id, 'hash' => $pickupInfo['guid'], 'cancel_ref' => $pickupInfo['id'], 'carrier_id' => $data['carrier_id'], 'pickup_date' => $data['pickup_date'], 'from' => $data['from'], 'to' => $data['to'], 'address_info' => collect($address)],
        );

        return $this->successful('Created-Updated Successfully');
    }

    public function cancel(PickuptRequest $request)
    {
        $pickup = Pickup::where('id', $request->pickup_id, )->where('carrier_id', $request->carrier_id)->first();
        $this->cancelPickup($pickup->carrier_name, $pickup);

        $pickup = Pickup::findOrfail($request->pickup_id);
        $pickup->status = 'CANCELD';
        $pickup->save();

        return $this->successful('The pickup has been canceled successfully');
    }
}
