<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarcaController extends Controller
{
    use HasPagination;

    /**
     * Agrega la URL completa del logo a la marca (misma lógica que ArticuloController::addImageUrl).
     * El logo se guarda solo como nombre de archivo en BD; la URL se sirve por /api/marcas/imagen/{filename}.
     */
    private function addLogoUrl($marca)
    {
        if ($marca->logo) {
            $baseUrl = rtrim(config('app.url'), '/');
            if (substr($baseUrl, -4) === '/api') {
                $baseUrl = rtrim(substr($baseUrl, 0, -4), '/');
            }
            if (strpos($marca->logo, '/') !== false) {
                $filename = basename($marca->logo);
            } else {
                $filename = $marca->logo;
            }
            $marca->logo_url = $baseUrl . '/api/marcas/imagen/' . rawurlencode($filename);
        } else {
            $marca->logo_url = null;
        }
        return $marca;
    }

    /**
     * Sirve la imagen/logo de una marca (misma lógica que ArticuloController::serveImage).
     * No requiere storage link; busca en storage y public.
     */
    public function serveImage($filename)
    {
        try {
            $originalFilename = urldecode($filename);
            $filename = basename($originalFilename);
            if (empty($filename)) {
                return response()->json(['error' => 'Nombre de archivo no válido'], 400);
            }

            $basePaths = [
                storage_path('app/public/marcas'),
                public_path('storage/marcas'),
                storage_path('app/public'),
                public_path('storage'),
            ];

            $filenameVariations = [
                $filename,
                urldecode($filename),
                rawurldecode($filename),
            ];

            $filePath = null;

            foreach ($basePaths as $basePath) {
                $marcasPath = (str_ends_with($basePath, 'marcas')) ? $basePath : $basePath . '/marcas';
                if (is_dir($marcasPath)) {
                    foreach ($filenameVariations as $variation) {
                        $testPath = $marcasPath . '/' . $variation;
                        if (file_exists($testPath) && is_file($testPath)) {
                            $filePath = $testPath;
                            break 2;
                        }
                    }
                }
                foreach ($filenameVariations as $variation) {
                    $testPath = $basePath . '/' . $variation;
                    if (file_exists($testPath) && is_file($testPath)) {
                        $filePath = $testPath;
                        break 2;
                    }
                }
            }

            if (!$filePath) {
                $storagePaths = [
                    'marcas/' . $filename,
                    $filename,
                ];
                foreach ($storagePaths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        $fullPath = Storage::disk('public')->path($path);
                        if (file_exists($fullPath) && is_file($fullPath)) {
                            $filePath = $fullPath;
                            break;
                        }
                    }
                }
            }

            if (!$filePath) {
                $marcasDir = storage_path('app/public/marcas');
                if (is_dir($marcasDir)) {
                    $files = @scandir($marcasDir) ?: [];
                    $searchBase = pathinfo($filename, PATHINFO_FILENAME);
                    $searchExt = pathinfo($filename, PATHINFO_EXTENSION);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $fileBase = pathinfo($file, PATHINFO_FILENAME);
                        $fileExt = pathinfo($file, PATHINFO_EXTENSION);
                        if (str_contains($fileBase, $searchBase) && strtolower($fileExt) === strtolower($searchExt)) {
                            $testPath = $marcasDir . '/' . $file;
                            if (file_exists($testPath) && is_file($testPath)) {
                                $filePath = $testPath;
                                break;
                            }
                        }
                    }
                }
            }

            if (!$filePath) {
                return response()->json([
                    'error' => 'Imagen no encontrada',
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'hint' => 'El archivo no existe en el servidor. Verifique que el archivo fue subido correctamente.'
                ], 404);
            }

            $mimeType = @mime_content_type($filePath);
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

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cargar la imagen',
                'message' => $e->getMessage(),
                'filename' => $filename ?? 'unknown'
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Marca::query();

            $searchableFields = [
                'id',
                'nombre'
            ];

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'nombre', 'created_at'], 'id', 'desc');

            $response = $this->paginateResponse($query, $request, 15, 100);

            // Agregar logo_url a cada marca (igual que articulos con fotografia_url)
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getContent(), true);
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data']['data'])) {
                    foreach ($responseData['data']['data'] as &$marcaItem) {
                        $marcaObj = (object) $marcaItem;
                        $this->addLogoUrl($marcaObj);
                        $marcaItem = (array) $marcaObj;
                    }
                    return response()->json($responseData);
                }
            }

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ]
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:marcas',
            'logo' => [
                'nullable',
                Rule::when($request->hasFile('logo'), ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']),
            ],
            'estado' => 'nullable|boolean',
        ]);

        $data = $request->only(['nombre', 'estado']);

        if ($request->hasFile('logo')) {
            try {
                $file = $request->file('logo');
                if (!$file->isValid()) {
                    \Log::error('Archivo de logo inválido (marca store)', ['error' => $file->getError()]);
                    throw new \Exception('El archivo de imagen no es válido');
                }
                $directory = 'marcas';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                $nombreMarca = $request->input('nombre', 'marca');
                $slug = Str::slug($nombreMarca);
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = $slug . '_' . time() . '.' . $ext;
                $saved = $file->storeAs($directory, $filename, 'public');
                if (!$saved) {
                    \Log::error('Error al guardar logo (marca store)', ['filename' => $filename]);
                    throw new \Exception('Error al guardar la imagen');
                }
                $data['logo'] = $filename;
                \Log::info('Logo de marca guardado', ['filename' => $filename, 'path' => $saved]);
            } catch (\Exception $e) {
                \Log::error('Error al procesar logo en store (marca)', ['error' => $e->getMessage()]);
                unset($data['logo']);
            }
        }

        $marca = Marca::create($data);
        $this->addLogoUrl($marca);
        return response()->json($marca, 201);
    }

    public function show(Marca $marca)
    {
        $this->addLogoUrl($marca);
        return response()->json($marca);
    }

    public function update(Request $request, Marca $marca)
    {
        if ($request->hasFile('logo') && !$request->filled('nombre') && $marca->nombre) {
            $request->merge(['nombre' => $marca->nombre]);
        }

        $request->validate([
            'nombre' => 'required|string|max:100|unique:marcas,nombre,' . $marca->id,
            'logo' => [
                'nullable',
                Rule::when($request->hasFile('logo'), ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']),
            ],
            'estado' => 'nullable|boolean',
        ]);

        $data = $request->only(['nombre', 'estado']);

        if ($request->hasFile('logo')) {
            try {
                $file = $request->file('logo');
                if (!$file->isValid()) {
                    \Log::error('Archivo de logo inválido (marca update)', ['error' => $file->getError()]);
                    throw new \Exception('El archivo de imagen no es válido');
                }
                if ($marca->logo) {
                    if (strpos($marca->logo, '/') !== false) {
                        Storage::disk('public')->delete($marca->logo);
                    } else {
                        Storage::disk('public')->delete('marcas/' . $marca->logo);
                    }
                }
                $directory = 'marcas';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                $nombreMarca = $request->input('nombre', $marca->nombre ?? 'marca');
                $slug = Str::slug($nombreMarca);
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = $slug . '_' . time() . '.' . $ext;
                $saved = $file->storeAs($directory, $filename, 'public');
                if (!$saved) {
                    \Log::error('Error al guardar logo (marca update)', ['filename' => $filename]);
                    throw new \Exception('Error al guardar la imagen');
                }
                $data['logo'] = $filename;
                \Log::info('Logo de marca actualizado', ['filename' => $filename, 'path' => $saved]);
            } catch (\Exception $e) {
                \Log::error('Error al procesar logo en update (marca)', ['error' => $e->getMessage()]);
            }
        }

        $marca->update($data);
        $this->addLogoUrl($marca);
        return response()->json($marca);
    }

    public function destroy(Marca $marca)
    {
        $marca->delete();
        return response()->json(null, 204);
    }
    public function toggleStatus(Marca $marca)
    {
        $marca->estado = !$marca->estado;
        $marca->save();
        return response()->json([
            'success' => true,
            'message' => $marca->estado ? 'Marca activada' : 'Marca desactivada',
            'data' => $marca
        ]);
    }
}
