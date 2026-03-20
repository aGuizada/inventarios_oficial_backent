<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Respuestas JSON seguras para la API (sin filtrar detalles internos en producción).
 */
final class ApiError
{
    public static function serverError(
        \Throwable $e,
        string $publicMessage = 'Ha ocurrido un error. Por favor, intente de nuevo.',
        string $logContext = ''
    ): JsonResponse {
        \Log::error($logContext ?: 'Error en API', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $payload = [
            'success' => false,
            'message' => $publicMessage,
        ];

        if (config('app.debug')) {
            $payload['error'] = $e->getMessage();
            $payload['file'] = basename($e->getFile());
            $payload['line'] = $e->getLine();
        }

        return response()->json($payload, 500);
    }
}
