<?php
namespace App\Traits;
use Illuminate\Support\Facades\Response;

trait responseHandler
{  
    public static function response($data = [], $msg = '', $status = 200)
    {
        $response = [
            'meta' => [
                'code' => $status,
                'msg' => $msg
            ]
        ];
        if(!empty($data))
            $response['data'] = $data;
        return Response::make($response, 200);
    }

    public static function successful($msg = '')
    {
        $response = [
            'meta' => [
                'code' => 200,
                'msg' => ($msg != '') ? $msg : 'Created Sucessfully'
            ]
        ];

        return Response::make($response, 200);
    }

    public static function notFound()
    {
        $response = [
            'meta' => [
                'code' => 400,
                'msg' => 'Not Found'
            ]
        ];
        return Response::make($response, 200);
    }

    public static function error($msg, $status_code = 400)
    {
        $response = [
            'meta' => [
                'code' => $status_code,
                'msg' => ($msg != '') ? $msg : 'Unexpected Error'
            ]
        ];
        return Response::make($response, 200);
    }
}
