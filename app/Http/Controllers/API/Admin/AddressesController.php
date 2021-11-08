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
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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
        $addresses = $addresses->where('id', $id);
        if ($addresses->first() == null)
            throw new InternalException('Payment id not Exists');
        $current = $addresses->keys()->first();
        $addresses[$current] = $data;
        $addresses = $addresses->replaceRecursive($addresses);
        $merchant->update(['payment_methods' => $addresses]);
        return $this->successful('Updated Sucessfully');

    }
}
