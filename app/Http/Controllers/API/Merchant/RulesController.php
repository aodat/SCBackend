<?php

namespace App\Http\Controllers\API\Merchant;

use App\Exceptions\InternalException;
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
        $merchant = $this->getMerchentInfo();
        $result = collect($merchant->select('rules')->first()->rules);
        $counter = $result->max('id') ?? 0;

        $data = $request->validated();
        $data['id'] = ++$counter;
        $data['is_active'] = true;
        $data['created_at'] = Carbon::now();

        $merchant->update(['rules' => $result->merge([$data])]);
        return $this->successful('Created Successfully');
    }

    public function status($id, RulesRequest $request)
    {
        $merchant = $this->getMerchentInfo();
        $rules = collect($merchant->rules);
        $rule = $rules->where('id', $id);

        if ($rule->first() == null)
            throw new InternalException('Rule id not Exists', 400);

        $current = $rule->keys()->first();
        $data = $rule->toArray()[$current];
        $data['updated_at'] =  Carbon::now();
        $data['is_active'] =  $request->is_active;
        $rules[$current] = $data;
        
        $merchant->update(['rules' => $rules]);
        return $this->successful("Success Update");
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
