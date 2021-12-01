<?php

namespace App\Exceptions;

use Exception;

class InternalException extends Exception
{
    protected $msg, $code, $requested, $response;
    public function __construct($msg, $code = 422, $requested = [], $response = [])
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
        return ['requested' => $this->requested, 'response' => $this->response];
    }

}
