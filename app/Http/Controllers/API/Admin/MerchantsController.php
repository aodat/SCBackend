<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;

class MerchantsController extends Controller
{
    public function index(Request $request)
    {

    }

    public function store()
    {
        Merchant::create(
            [

            ]
        );
        $this->successful('Merchant Created Suceffuly');
    }
}
