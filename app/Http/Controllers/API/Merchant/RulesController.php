<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Requests\Merchant\RulesRequest;
use App\Models\Merchant;

class RulesController extends MerchantController
{
    function index(RulesRequest $request)
    {
        $rules = $this->getMerchentInfo()->rules;
        return $this->response($rules, 'Data Retrieved Successfully');
    }

    function store(RulesRequest $request)
    {

    }

    function edit(RulesRequest $request)
    {
    }

    function delete(RulesRequest $request)
    {
    }
}
