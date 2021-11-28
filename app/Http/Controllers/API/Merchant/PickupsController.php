<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\PickuptRequest;
use Illuminate\Support\Facades\DB;

use App\Models\Pickup;
use App\Models\Carriers;

class PickupsController extends MerchantController
{
    public function index(PickuptRequest $request)
    {
        $filters = $request->json()->all();

        $since = $filters['created_at']['since'] ?? Carbon::today()->subDays(3)->format('Y-m-d');;
        $until = $filters['created_at']['until'] ?? Carbon::today()->format('Y-m-d');

        $pickupID = $request->pickup_id ?? null;
        $carrierID = $request->carrier_id ?? null;

        $pickup = Pickup::whereBetween('created_at', [$since . " 00:00:00", $until . " 23:59:59"])->where('merchant_id', $request->user()->merchant_id);

        if ($pickupID != null)
            $pickup->where('id', $pickupID);

        if ($carrierID != null)
            $pickup->where('carrier_id', $carrierID);
        $pickup->where('merchant_id', $request->user()->id);

        $paginated = $pickup->paginate(request()->perPage ?? 10);
        return $this->pagination($paginated);
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

        return $this->successful('The pickup has been canceled successfully');
    }
}
