<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\App;

class CarriersException extends Exception
{
    protected $msg, $code, $requested, $response;
    public function __construct($msg, $requested = [], $response = [])
    {
        $this->msg = $msg;
        $this->requested = $requested;
        $this->response = $response;
    }

    public function render($rquest)
    {
        $response = [
            'meta' =>
            [
                'code' => 422,
                'msg' => $this->msg,
                'request_id' => App::make('request_id')
            ]
        ];
        return response()->json($response);
    }

    public function context()
    {
        return ['request_id' => App::make('request_id'), 'requested' => $this->requested, 'response' => collect($this->response)];
    }
}
