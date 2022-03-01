<?php

namespace App\Http\Middleware;

use App\Exceptions\InternalException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Shipment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $type = (strpos(Request()->route()->uri, 'shipments/domestic/create') !== false) ? 'DOM' : 'EXP';
        $merchantInfo = App::make('merchantInfo');

        if ($type == 'EXP') {
            if (!$merchantInfo->is_exp_enabled)
                throw new InternalException('Create Express Shipment Not Allowed, Please Contact Administrator');

            $request->merge(
                [
                    'group' => 'EXP',
                    'merchant_id' => Request()->user()->merchant_id,
                    'created_by' => Request()->user()->id,
                    'status' => 'DRAFT',
                    'resource' => Request()->header('Agent') ?? 'WEB'
                ]
            );
        } else if ($type == 'DOM') {
            dd('DOM');
        }
        return $next($request);
    }
}
