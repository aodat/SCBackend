<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use Illuminate\Support\Facades\DB;

use App\Models\Pickup;
use App\Models\Carriers;
use Carbon\Carbon;

class PickupsController extends MerchantController
{
    private $status = [
        'DONE' => 0, 'PROCESSING' => 0, 'CANCELD' => 0
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
        if ($pickupID != null)
            $pickup->where('id', $pickupID);

        if ($status != null)
            $pickup->whereIn('status', $status);

        if ($carrierID != null)
            $pickup->where('carrier_id', $carrierID);


        $tabs = DB::table('pickups')
            ->where('merchant_id', Request()->user()->merchant_id)
            ->select('status', DB::raw(
                'count(status) as counter'
            ))
            ->groupBy('status')
            ->pluck('counter', 'status');

        $tabs = collect($this->status)->merge(collect($tabs));
        return $this->pagination($pickup->paginate(request()->per_page ?? 10), ['tabs' => $tabs]);
    }

    public function show($id, PickuptRequest $request)
    {
        $data = Pickup::findOrFail($id);
        return $this->response($data, 'Data Retrieved Sucessfully');
    }

    public function store(PickuptRequest $request)
    {
        $merchentInfo = $this->getMerchentInfo();
        $merchentAddresses = collect($merchentInfo->addresses);

        $data = $request->validated();
        $address = $merchentAddresses->where('id', '=', $data['address_id'])->first();
        if ($address == null)
            return $this->error('address id is not valid', 400);
        $provider = Carriers::findOrfail($data['carrier_id'])->name;
        DB::transaction(function () use ($data, $address, $provider, $merchentInfo) {
            $pickupInfo = $this->generatePickup($provider, $data['pickup_date'], $address);

            $data['merchant_id'] = $merchentInfo->id;
            $data['hash'] = $pickupInfo['guid'];
            $data['cancel_ref'] = $pickupInfo['id'];

            Pickup::updateOrCreate(
                ['merchant_id' => $merchentInfo->id, 'hash' => $pickupInfo['guid'], 'carrier_id' => $data['carrier_id']],
                ['merchant_id' => $merchentInfo->id, 'hash' => $pickupInfo['guid'], 'cancel_ref' => $pickupInfo['id'], 'carrier_id' => $data['carrier_id'], 'pickup_date' => $data['pickup_date'], 'address_id' => $data['address_id']],
            );
        });

        return $this->successful('Created-Updated Successfully');
    }

    public function cancel(PickuptRequest $request)
    {
        $pickup = Pickup::getPickupCarrires($request->user()->merchant_id, $request->pickup_id, $request->carrier_id, false);
        $this->cancelPickup($pickup->name, $pickup);

        $pickup = Pickup::findOrfail($request->pickup_id);
        $pickup->status = 'CANCELD';
        $pickup->save();

        return $this->successful('The pickup has been canceled successfully');
    }
}
