<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarriersRequest;

use App\Models\Carriers;

use Illuminate\Support\Facades\DB;

class CarriersController extends Controller
{
    public function index(CarriersRequest $request)
    {
        $id = $request->id ?? '';
        $name = $request->name ?? '';
        $email = $request->email;

        $carriers = DB::table('carriers');
        if ($name)
            $carriers->where('name', 'like', '%' . $name . '%');
        if ($email)
            $carriers->where('email', 'like', '%' . $email . '%');
        if ($id)
            $carriers->where('id', $id);

        $paginated = $carriers->paginate(request()->perPage ?? 10);
        return $this->pagination($paginated);
    }

    public function show($carrier_id, CarriersRequest $request)
    {
        $carrier = Carriers::findOrFail($carrier_id);
        return $this->response($carrier, 'Data Retrieved Successfully');
    }

    public function store(CarriersRequest $request)
    {
        $data = $request->validated();
        Carriers::create($data);
        return $this->successful('Create Successfully');
    }

    public function update(CarriersRequest $request)
    {
        $data = $request->validated();
        $carrier = Carriers::findOrFail($data['id']);
        $carrier->country_code = $data['country_code'];
        $carrier->currency_code = $data['currency_code'];
        $carrier->is_active = $data['is_active'];
        $carrier->save();

        return $this->successful('Updated Successfully');
    }
}
