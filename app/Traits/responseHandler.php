<?php
namespace App\Traits;
use Illuminate\Support\Facades\Response;

trait ResponseHandler
{  
    public static function response($data = [], $msg = '', $status = 200,$isPagination = false)
    {
        $response = null;
        if($isPagination) {
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
        }
        else if(!empty($data))
            $response['data'] = $data;
        
        $response['meta']['code'] =  $status;
        $response['meta']['msg'] =  $msg;
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
