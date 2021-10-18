<?php
namespace App\Http;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;

class responseHandler
{  
    /*
    public static function sendResponse($data = '', $code = 200, $withoutData = false)
    {
        if (in_array($code, [201, 202, 204, 404]))
            return Response::make(null, $code);

        if ($data === null)
            return Response::make(null, 404);

        if (empty($data))
            return Response::make(null, 204);

        if ($data instanceof Collection)
            if ($data->isEmpty())
                return Response::make(null, 204);

        if ($withoutData == false)
            return Response::json([
                'data' => $data,
            ], $code);

        return Response::json($data, $code);
    }
    */


    public static function response($response, $code)
    {
        return Response::json($response, $code);
    }

    public static function successful()
    {
        return Response::make(null, 204);
    }

    public static function notFound()
    {
        return Response::make(null, 404);
    }

    public static function error($data, $status_code = 400)
    {
        return Response::make($data, $status_code);
    }
}
