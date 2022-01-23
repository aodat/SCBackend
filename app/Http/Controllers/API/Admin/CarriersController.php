<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarriersRequest;
use App\Models\Carriers;

class CarriersController extends Controller
{
    public function index(CarriersRequest $request)
    {
        $id = $request->id ?? '';
        $name = $request->name ?? '';
        $email = $request->email;

        $carriers = Carriers::paginate(request()->per_page ?? 30);
        if ($name) {
            $carriers->where('name', 'like', '%' . $name . '%');
        }

        if ($email) {
            $carriers->where('email', 'like', '%' . $email . '%');
        }

        if ($id) {
            $carriers->where('id', $id);
        }

        return $this->pagination($carriers);
    }

    public function show(CarriersRequest $request)
    {
        $carrier = Carriers::findOrFail($request->carrier_id);
        return $this->response($carrier, 'Data Retrieved Successfully');
    }

    public function store(CarriersRequest $request)
    {
        $data = $request->validated();
        Carriers::create($data);
        return $this->successful('Created Successfully');
    }

    public function update(CarriersRequest $request)
    {
        $carrier = Carriers::findOrFail($request->carrier_id);
        $carrier->country_code = $request->country_code;
        $carrier->currency_code = $request->currency_code;
        $carrier->is_active = $request->is_active;
        $carrier->phone = $request->phone;
        $carrier->save();

        return $this->successful('Updated Successfully');
    }
}
