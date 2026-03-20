<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configuración de CORS para permitir peticiones desde el frontend
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Permitir CORS para todas las rutas API
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // No configurar redirectGuestsTo para evitar que intente usar route('login')
        // El manejador de excepciones se encargará de devolver JSON para APIs
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejar excepciones de autenticación para APIs
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'No autenticado. Por favor, inicia sesión.',
                    'error' => 'Unauthenticated',
                ], 401);
            }
        });

        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*');
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                $msg = $e->getMessage();
                if ($msg === '' || str_contains($msg, 'This action is unauthorized')) {
                    $msg = 'No autorizado para realizar esta acción.';
                }

                return response()->json(['message' => $msg], 403);
            }

            return null;
        });

        // authorize() en Laravel 11+ suele exponerse como AccessDeniedHttpException en la respuesta API
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*')) {
                $msg = $e->getMessage();
                if ($msg === '' || str_contains($msg, 'This action is unauthorized')) {
                    $msg = 'No autorizado para realizar esta acción.';
                }

                return response()->json(['message' => $msg], $e->getStatusCode());
            }

            return null;
        });

        // Respuestas 500 genéricas en API (detalle solo con APP_DEBUG=true)
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            \Illuminate\Support\Facades\Log::error('API: excepción no controlada', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $payload = [
                'message' => 'Ha ocurrido un error en el servidor.',
                'error' => 'server_error',
            ];
            if (config('app.debug')) {
                $payload['debug'] = [
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        });
    })->create();
