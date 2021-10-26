<?php

namespace App\Exceptions;

use Exception;

class CarriersException extends Exception
{
   
    public function render($rquest)
    {       
        $response = ['meta' => ['code' => 500,'msg' => $this->getMessage()]];
        return response()->json($response);       
    }
}