<?php

namespace App\Http\Middleware;

use App\Exceptions\InternalException;
use Closure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

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

        try {
            $response = $next($request);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if (
            ($response->getStatusCode() == 500
                || (isset(json_decode($response->getContent())->meta->code) &&
                    json_decode($response->getContent())->meta->code > 399))

        ) {
            DB::rollBack();
        } else {

            DB::commit();
        }

        if (env('APP_ENV') == 'production' && $response->getStatusCode() == 500)
            throw new InternalException('Internal Server Error - ' . App::make('request_id'), 500);
        return $response;
    }


    public function terminate($request, $response)
    {
        $data['info'] = [
            'ip' => $request->ip(),
            'user_id' => $request->user()->id ?? null,
            'merchant_id' => $request->user()->merchant_id ?? null,
            'token' => $request->bearerToken() ?? null,
            'request_id' => App::make('request_id')
        ];

        $data['body'] = $request->all();
        $code = json_decode($response->getContent())->meta->code ?? null;
        if ($code > 300 && $code < 499)
            Log::debug('Shipcash Error : ', ['request' => $data, 'response' => json_decode($response->getContent())]);
        else if ($code == 500 || $response->getStatusCode() == 500)
            Log::error('Inernal server Error :', ['request' => $data, 'response' => json_decode($response->getContent())]);
    }
}
