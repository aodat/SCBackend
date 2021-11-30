<?php

namespace App\Http\Middleware;

use App\Exceptions\InternalException;
use Carbon\Carbon;
use Closure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function terminate($request, $response)
    {
        $data['info'] = [
            'ip' => $request->ip(),
            'user_id' => $request->user()->id ?? null,
            'merchant_id' => $request->user()->merchant_id ?? null,
            'token' => $request->bearerToken() ?? null
        ];

        $data['body'] = $request->all();
        $code = json_decode($response->getContent())->meta->code;

        if ($code <= 204)
            Log::info('Sucess : ', ['request' => $data, 'response' => json_decode($response->getContent())]);
        else if ($code > 300 && $code < 499)
            Log::debug('Shipcash Error : ', ['request' => $data, 'response' => json_decode($response->getContent())]);
        else
            Log::error('Inernal server Error :', ['request' => $data, 'response' => json_decode($response->getContent())]);
    }
}
