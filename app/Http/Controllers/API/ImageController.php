<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /**
     * Sirve imágenes de artículos directamente desde storage
     * Útil cuando el enlace simbólico no está configurado
     */
    public function serveArticuloImage($filename)
    {
        try {
            // Verificar que el archivo existe
            $path = 'articulos/' . $filename;
            
            if (!Storage::disk('public')->exists($path)) {
                // Si no existe, intentar sin el prefijo 'articulos/'
                $path = $filename;
                if (!Storage::disk('public')->exists($path)) {
                    return response()->json([
                        'error' => 'Imagen no encontrada'
                    ], 404);
                }
            }

            // Obtener la ruta completa del archivo
            $filePath = Storage::disk('public')->path($path);
            
            // Verificar que el archivo existe físicamente
            if (!file_exists($filePath)) {
                return response()->json([
                    'error' => 'Archivo no encontrado en el servidor'
                ], 404);
            }

            // Determinar el tipo MIME
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $mimeType = $mimeTypes[strtolower($extension)] ?? 'image/jpeg';
            }

            // Devolver el archivo con los headers correctos
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000', // Cache por 1 año
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cargar la imagen',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
