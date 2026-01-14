<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

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
                    'error' => 'Unauthenticated'
                ], 401);
            }
        });
        
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*');
        });
    })->create();
