<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\InvalidRequestException;
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
        if ($exception instanceof ModelNotFoundException) {
            $response['meta']['code'] = 404;
            $response['meta']['msg'] = class_basename($exception->getModel()) . ' ID Not Found';
            return Response::make($response);
        } elseif ($exception instanceof ValidationException) {
            $response['error'] = $exception->errors();
            $response['meta']['code'] = 400;
            $response['meta']['msg'] = 'Valiation error';
            return Response::make($response);
        } else if ($exception instanceof AuthorizationException) {
            $response['meta']['code'] = 403;
            $response['meta']['msg'] = 'Unauthorized request';
            return Response::make($response);
        } else if ($exception instanceof \Swift_TransportException) {
            $response['meta']['code'] = 500;
            $response['meta']['msg'] = 'Request to AWS SES API failed';
            return Response::make($response);
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
