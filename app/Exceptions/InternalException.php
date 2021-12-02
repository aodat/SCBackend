<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\App;

class InternalException extends Exception
{
    protected $msg, $code, $requested, $response;
    public function __construct($msg, $code = 400, $requested = [], $response = [])
    {
        $this->msg = $msg;
        $this->code = $code;
        $this->requested = $requested;
        $this->response = $response;
    }

    public function render($rquest)
    {
        $response = ['meta' => ['code' => $this->code, 'msg' => $this->msg]];
        return response()->json($response);
    }

    public function context()
    {
        return ['request_id' => App::make('request_id'), 'requested' => $this->requested, 'response' => $this->response];
    }
}
