<?php

namespace App\Exceptions;

use Exception;

class InternalException extends Exception
{
    public function render($request)
    {
        $response = ['meta' => ['code' => 422, 'msg' => $this->getMessage()]];
        return response()->json($response);
    }
}
