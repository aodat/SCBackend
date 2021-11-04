<?php

namespace App\Http\Controllers\API;

use App\Events\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;

use App\Models\User;

class TeamController extends Controller
{
    //
    public function inviteMember(TeamRequest $request)
    {
        $user = User::create(
            [
                'merchant_id' => $request->user()->merchant_id,
                'name' => null,
                'email' => $request->email,
                'password' => null,
                'phone' => null
            ]
        );
        // event(new InviteUser($user);
    }
}
