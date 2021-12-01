<?php

namespace App\Exceptions;

use Exception;

class CarriersException extends Exception
{
    protected $msg, $code, $requested, $response;
    public function __construct($msg, $code, $requested = [], $response = [])
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
}
