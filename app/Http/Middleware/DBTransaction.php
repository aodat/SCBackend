<?php

namespace App\Http\Middleware;

use App\Exceptions\InternalException;
use Closure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DBTransaction
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


        DB::beginTransaction();
        $response = $next($request);
        if ($response->getStatusCode() == 500) {
            DB::rollBack();
            throw new InternalException('Unexpected Error', 500);
        }

        return  $response;
    }
}
