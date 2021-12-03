<?php

namespace App\Traits;

use Illuminate\Support\Facades\Response;

trait ResponseHandler
{

    public static function response($data, $msg, $code = 200)
    {
        $code = (!collect($data)->isEmpty()) ? $code : 204;
        $response['data'] = $data;
        $response['meta']['msg'] =  $msg;
        $response['meta']['code'] =  $code;
        return Response::make($response,  200);
    }

    public static function pagination($data)
    {
        $pagination = $data->toArray();
        $response['data'] = $pagination['data'];
        $response['pagination_meta'] = [
            'current_page' => $pagination['current_page'],
            'last_page' => $pagination['last_page'],
            'total' => $pagination['total'],
            'first_page_url' => $pagination['first_page_url'],
            'next_page_url' => $pagination['next_page_url'],
            'per_page' => intval($pagination['per_page'])
        ];
        $response['meta']['code'] = 200;
        $response['meta']['msg'] = "Data retrieved successfully";
        return Response::make($response, 200);
    }

    public static function successful($msg = '')
    {
        $response = [
            'meta' => [
                'code' => 200,
                'msg' => ($msg != '') ? $msg : 'Created Successfully'
            ]
        ];

        return Response::make($response, 200);
    }

    public static function notFound()
    {
        $response = [
            'meta' => [
                'code' => 404,
                'msg' => 'Not Found'
            ]
        ];
        return Response::make($response, 200);
    }

    public static function error($msg, $code = 400)
    {
        $response = [
            'meta' => [
                'code' => $code,
                'msg' => $msg
            ]
        ];
        return Response::make($response, 200);
    }
}
