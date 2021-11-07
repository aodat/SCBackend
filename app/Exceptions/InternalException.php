<?php

namespace App\Exceptions;

use Exception;

class InternalException extends Exception
{
    public function render($request)
    {
        $response = ['meta' => ['code' => $this->getCode(), 'msg' => $this->getMessage()]];
        return response()->json($response);
    }
}
