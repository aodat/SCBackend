<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Models\Merchant;
use App\Exceptions\InternalException;

class CheckMerchant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle(Request $request, Closure $next)
    {

        $Merchaninfo = Merchant::findOrFail(auth()->user()->merchant_id);
        if (auth()->user()->status == 'in_active' || (!$Merchaninfo->is_active))
            throw new InternalException('Your Account is inactive please contact us', 403);
        return $next($request);
    }
}
