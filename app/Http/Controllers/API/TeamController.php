<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;

use App\Models\User;

use App\Notifications\InviteUserNotification as InviteUserNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Laravel\Passport\Token;

class TeamController extends Controller
{

    public function inviteMember(TeamRequest $request)
    {

        DB::transaction(function () use ($request) {
            $password = Str::random(8);
            $user = User::create(
                [
                    'merchant_id' => $request->user()->merchant_id,
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($password),
                    'phone' => null
                ]
            );
            $user->notify(new InviteUserNotification($user, $password));
        });

        return $this->successful();
    }

    public function changeMemberRole(TeamRequest $request)
    {
        $data = $request->validated();

        $user = User::findOrFail($data['id']);
        $user->role = $data['scope'];
        $user->role_member = implode(',', $data['role']);
        $user->save();

        return $this->successful('Updated Successfully');
    }

    public function deleteMember($user_id, TeamRequest $request)
    {
        $user = User::findOrFail($user_id);
        $user->status = 'inactive';
        $user->save();

        // Delete all user session
        Token::where('user_id', $user_id)->update(['revoked' => true]);

        return $this->successful('Deleted Successfully');
    }
}
