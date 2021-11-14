<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

use Illuminate\Support\Facades\Response;

use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function render($request, Throwable $exception)
    {
        $response = [
            'meta' => [
                'code' => 200,
                'msg' => 'Unexpected Error'
            ]
        ];
        if ($exception instanceof ModelNotFoundException) {
            $response['meta']['code'] = 404;
            $response['meta']['msg'] = 'File Not Found';
            return Response::make($response);
        } elseif ($exception instanceof ValidationException) {
            $response['error'] = $exception->errors();
            $response['meta']['code'] = 422;
            return Response::make($response);
        } else if ($exception instanceof AuthorizationException) {
            return Response::make(null, 403);
        }
        return parent::render($request, $exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
