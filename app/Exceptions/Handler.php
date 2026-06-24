<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if (!$request->is('api/*')) return null;

            $path = '/' . $request->path();
            $ts   = now()->toISOString();

            if ($e instanceof DomainException) {
                return response()->json([
                    'error' => ['code' => $e->code, 'message' => $e->getMessage(), 'details' => $e->details],
                    'path'  => $path,
                    'timestamp' => $ts,
                ], $e->statusCode);
            }

            if ($e instanceof LaravelValidationException) {
                return response()->json([
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Données invalides.', 'details' => $e->errors()],
                    'path'  => $path,
                    'timestamp' => $ts,
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Non authentifié.', 'details' => null],
                    'path'  => $path,
                    'timestamp' => $ts,
                ], 401);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Route introuvable.', 'details' => null],
                    'path'  => $path,
                    'timestamp' => $ts,
                ], 404);
            }

            return response()->json([
                'error' => ['code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Une erreur inattendue est survenue.', 'details' => null],
                'path'  => $path,
                'timestamp' => $ts,
            ], 500);
        });
    }
}
