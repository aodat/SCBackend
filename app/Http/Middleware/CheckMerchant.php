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
        $allowedPlatform = ['WEB', 'PLUGIN', 'API'];

        $Merchaninfo = Merchant::findOrFail(auth()->user()->merchant_id);
        if (auth()->user()->status == 'in_active' || (!$Merchaninfo->is_active))
            throw new InternalException('Your Account is in active please contact us', 403);

        $header = $request->header('agent') ?? 'API';
        if (!in_array($header, $allowedPlatform))
            $header = 'API';

        $request->request->add(['resource' => $header]);
        return $next($request);
    }
}
