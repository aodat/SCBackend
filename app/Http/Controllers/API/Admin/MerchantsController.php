<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MerchantRequest;

use App\Models\Merchant;

use Illuminate\Support\Facades\DB;

class MerchantsController extends Controller
{
    public function index(MerchantRequest $request)
    {
        $id = $request->id ?? '';
        $name = $request->name ?? '';
        $email = $request->email;

        $merchants = DB::table('merchants');
        if ($name)
            $merchants->where('name', 'like', '%' . $name . '%');
        if ($email)
            $merchants->where('email', 'like', '%' . $email . '%');
        if ($id)
            $merchants->where('id', $id);

        $paginated = $merchants->paginate(request()->per_page ?? 30);
        return $this->pagination($paginated);
    }

    public function show(MerchantRequest $request, $id)
    {
        $merchant = Merchant::findOrFail($id);
        return $this->response($merchant, 'Data Retrieved Successfully', 200, false);
    }

    public function update(MerchantRequest $request)
    {
        $merchant = Merchant::findOrFail($request->merchant_id);
        $merchant->type = $request->type;
        $merchant->is_active = $request->is_active;
        $merchant->save();

        return $this->successful('Updated Successfully');
    }

    public function merchantConfig(MerchantRequest $request)
    {
        $data = $request->validated();

        $merchant = Merchant::findOrFail($data['merchant_id']);
        $type = $data['type'];
        return $this->response($merchant->$type, 'Data Retrieved Successfully');
    }

    public function domesticRates(MerchantRequest $request)
    {
        dd($request->validated());
    }

}
