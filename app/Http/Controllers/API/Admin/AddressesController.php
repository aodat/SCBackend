<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\InternalException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Merchant;
use App\Http\Requests\Admin\AddressesRequests;
use Carbon\Carbon;

class AddressesController extends Controller
{
    public function index(AddressesRequests $request)
    {
        $data  = Merchant::find($request->merchant_id)->addresses;
        return $this->response($data, "Success get addresse to specified merchant");
    }

    public function update(AddressesRequests $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? null;
        $data['updated_at'] = Carbon::now();
        $merchant_id = $data['merchant_id'];

        unset($data['merchant_id']);

        $merchant = Merchant::findOrFail($merchant_id);
        $addresses = $merchant->addresses;

        $addresses = collect($addresses);
        $addresse = $addresses->where('id', $id);

        if ($addresse->first() == null)
            throw new InternalException('addresse id not Exists');

        $current = $addresse->keys()->first();
        $addresses[$current] = $data;
        $merchant->update(['addresses' => $addresses]);
        return $this->successful('Updated Sucessfully');
    }
}