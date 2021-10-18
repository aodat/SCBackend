<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function checkOTP(Request $request)
    {
        return ($request->user());
    }
}
