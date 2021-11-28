<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\RulesRequest;
use App\Models\Merchant;
use Carbon\Carbon;

class RulesController extends MerchantController
{
    function index(RulesRequest $request)
    {
        $rules = $this->getMerchentInfo()->rules;
        return $this->response($rules, 'Data Retrieved Successfully');
    }

    function store(RulesRequest $request)
    {
        $data = $request->validated();
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->select('rules')->first()->rules);
        $counter = $result->max('id') ?? 0;

        $data['id'] = ++$counter;
        $data['created_at'] = Carbon::now();
        $data['update_at'] = null;

        $merchant->update(['rules' => $result->merge([$data])]);
        return $this->successful('Create Successfully');
    }

    public function delete($id, RulesRequest $request)
    {
        $merchant = $this->getMerchantInfo();
        $result = collect($merchant->select('rules')->first()->rules);

        $json = $result->reject(function ($value) use ($id) {
            if ($value['id'] == $id)
                return $value;
        });
        $json = array_values($json->toArray());
        $merchant->update(['rules' => collect($json)]);
        return $this->successful('Deleted Successfully');
    }
}
