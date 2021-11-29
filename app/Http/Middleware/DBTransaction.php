<?php

namespace App\Http\Middleware;

use App\Exceptions\InternalException;
use Closure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery\Expectation;
use Mpdf\Tag\Tr;

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
        $response = $next($request);
        if ($request->method() != 'GET') {
            DB::beginTransaction();
            if (
                isset(json_decode($response->getContent())->meta->code) &&
                json_decode($response->getContent())->meta->code > 399
            )
                DB::rollBack();
            else if ($response->getStatusCode() == 500) {
                DB::rollBack();
                throw new InternalException('Internal Server Error', 500);
            }
        }
        return  $response;
    }
}
