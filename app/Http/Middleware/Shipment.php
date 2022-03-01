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

        $add = [
            'group' => $type,
            'merchant_id' => Request()->user()->merchant_id,
            'created_by' => Request()->user()->id,
            'status' => 'DRAFT',
            'resource' => Request()->header('Agent') ?? 'WEB'
        ];

        if ($type == 'EXP') {
            if (!$merchantInfo->is_exp_enabled)
                throw new InternalException('Create Express Shipment Not Allowed, Please Contact Administrator');
            $request->merge($add);
        } else if ($type == 'DOM') {
            if (!$merchantInfo->is_dom_enabled)
                throw new InternalException('Create Domestic Shipment Not Allowed, Please Contact Administrator');
            // Check how man request with zero COD 
            $numberShipmentCOD = collect($request->all())->where('cod', 0)->count();

            if ($numberShipmentCOD > 0 && !$merchantInfo->is_cod_enabled)
                throw new InternalException('Create Domestic Shipment With No COD Amount Not Allowed, Please Contact Administrator');

            $requests = $request->all();
            foreach ($requests as $key => $value) {
                $requests[$key] = array_merge($value, $add);
            }
            $request->merge($requests);
        }
        return $next($request);
    }
}