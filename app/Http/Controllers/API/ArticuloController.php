<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelService;
use App\Exports\ArticulosExport;
use App\Imports\ArticulosImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
// use Intervention\Image\ImageManager;
// use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ArticuloController extends Controller
{
    use HasPagination;

    /**
     * Agrega la URL completa de la imagen al artículo
     * La fotografia se guarda solo como nombre de archivo, no como ruta completa
     */
    private function addImageUrl($articulo)
    {
        if ($articulo->fotografia) {
            // Obtener la URL base y asegurarse de que no termine en /api
            $baseUrl = rtrim(config('app.url'), '/');
            // Si termina en /api, removerlo para evitar duplicación
            if (substr($baseUrl, -4) === '/api') {
                $baseUrl = rtrim(substr($baseUrl, 0, -4), '/');
            }

            // Si ya tiene ruta completa (compatibilidad con datos antiguos)
            if (strpos($articulo->fotografia, '/') !== false) {
                // Extraer solo el nombre del archivo
                $filename = basename($articulo->fotografia);
            } else {
                // Solo nombre de archivo (nueva lógica)
                $filename = $articulo->fotografia;
            }

            // Codificar el nombre del archivo para la URL (maneja espacios y caracteres especiales)
            $filenameEncoded = rawurlencode($filename);

            // Usar endpoint de API para servir la imagen directamente desde storage
            $articulo->fotografia_url = $baseUrl . '/api/articulos/imagen/' . $filenameEncoded;
        } else {
            $articulo->fotografia_url = null;
        }
        return $articulo;
    }

    /**
     * Sirve imágenes de artículos directamente desde storage
     * No requiere storage link
     */
    public function serveImage($filename)
    {
        try {
            // Decodificar el nombre del archivo si viene codificado
            $originalFilename = urldecode($filename);

            // Limpiar el nombre del archivo para seguridad
            $filename = basename($originalFilename);

            // Rutas base donde buscar
            $basePaths = [
                storage_path('app/public/articulos'),
                public_path('storage/articulos'),
                storage_path('app/public'),
                public_path('storage'),
            ];

            // Variaciones del nombre de archivo a buscar
            $filenameVariations = [
                $filename,  // Nombre original
                urldecode($filename),  // Decodificado
                rawurldecode($filename),  // Raw decoded
            ];

            $filePath = null;

            // Buscar el archivo en todas las ubicaciones y variaciones posibles
            foreach ($basePaths as $basePath) {
                $articulosPath = $basePath . '/articulos';

                // Buscar en la carpeta articulos
                if (is_dir($articulosPath)) {
                    foreach ($filenameVariations as $variation) {
                        $testPath = $articulosPath . '/' . $variation;
                        if (file_exists($testPath) && is_file($testPath)) {
                            $filePath = $testPath;
                            break 2; // Salir de ambos loops
                        }
                    }
                }

                // También buscar directamente en la base path
                foreach ($filenameVariations as $variation) {
                    $testPath = $basePath . '/' . $variation;
                    if (file_exists($testPath) && is_file($testPath)) {
                        $filePath = $testPath;
                        break 2; // Salir de ambos loops
                    }
                }
            }

            // Si aún no se encontró, intentar buscar usando Storage facade
            if (!$filePath) {
                $storagePaths = [
                    'articulos/' . $filename,
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

            // Si aún no se encontró, buscar por coincidencia parcial (por si el nombre tiene variaciones)
            if (!$filePath) {
                $articulosDir = storage_path('app/public/articulos');
                if (is_dir($articulosDir)) {
                    $files = scandir($articulosDir);
                    $searchBase = pathinfo($filename, PATHINFO_FILENAME);
                    $searchExt = pathinfo($filename, PATHINFO_EXTENSION);

                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..')
                            continue;

                        $fileBase = pathinfo($file, PATHINFO_FILENAME);
                        $fileExt = pathinfo($file, PATHINFO_EXTENSION);

                        // Si coincide el nombre base y la extensión (ignorando timestamp)
                        if (
                            strpos($fileBase, $searchBase) !== false &&
                            strtolower($fileExt) === strtolower($searchExt)
                        ) {
                            $testPath = $articulosDir . '/' . $file;
                            if (file_exists($testPath) && is_file($testPath)) {
                                $filePath = $testPath;
                                break;
                            }
                        }
                    }
                }
            }

            // Si aún no se encontró, devolver 404
            if (!$filePath) {
                return response()->json([
                    'error' => 'Imagen no encontrada',
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'hint' => 'El archivo no existe en el servidor. Verifique que el archivo fue subido correctamente.'
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

    /**
     * Agrega URLs de imágenes a una colección de artículos
     */
    private function addImageUrlsToCollection($articulos)
    {
        if (is_array($articulos)) {
            return array_map([$this, 'addImageUrl'], $articulos);
        }
        return $articulos->map(function ($articulo) {
            return $this->addImageUrl($articulo);
        });
    }

    public function index(Request $request)
    {
        try {
            // Optimizar carga de relaciones: solo cargar campos necesarios
            $query = Articulo::with([
                'categoria:id,nombre',
                'proveedor:id,nombre',
                'medida:id,nombre_medida',
                'marca:id,nombre',
                'industria:id,nombre'
            ]);

            // Campos buscables: solo código y nombre del artículo
            $searchableFields = [
                'codigo',
                'nombre'
            ];

            // Aplicar búsqueda
            $query = $this->applySearch($query, $request, $searchableFields);

            // Campos ordenables
            $sortableFields = ['id', 'codigo', 'nombre', 'precio_venta', 'stock', 'created_at'];

            // Aplicar ordenamiento
            $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');

            // Aplicar paginación (sin límite máximo para catálogo)
            $response = $this->paginateResponse($query, $request, 15, 999999);

            // Agregar URLs de imágenes usando el método existente addImageUrl
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getContent(), true);
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data']['data'])) {
                    // Procesar cada artículo usando addImageUrl (mismo método que usan show, store, update)
                    foreach ($responseData['data']['data'] as &$articulo) {
                        // Convertir array a objeto temporal para usar addImageUrl
                        $articuloObj = (object) $articulo;
                        $this->addImageUrl($articuloObj);
                        // Convertir de vuelta a array y preservar fotografia_url
                        $articulo = (array) $articuloObj;
                    }
                    return response()->json($responseData);
                }
            }

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar artículos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Normalizar código antes de validar: si es string vacío o la palabra "null", convertir a null real
            if ($request->has('codigo') && ($request->codigo === '' || $request->codigo === null || $request->codigo === 'null')) {
                $request->merge(['codigo' => null]);
            }

            $request->validate([
                'categoria_id' => 'required|exists:categorias,id',
                'proveedor_id' => 'required|exists:proveedores,id',
                'medida_id' => 'required|exists:medidas,id',
                'marca_id' => 'required|exists:marcas,id',
                'industria_id' => 'required|exists:industrias,id',
                'codigo' => [
                    'nullable',
                    'string',
                    'max:255'
                ],
                'nombre' => 'required|string|max:255',
                'unidad_envase' => 'required|integer',
                'precio_costo_unid' => 'required|numeric',
                'precio_costo_paq' => 'required|numeric',
                'precio_venta' => 'required|numeric',
                'precio_uno' => 'nullable|numeric',
                'precio_dos' => 'nullable|numeric',
                'precio_tres' => 'nullable|numeric',
                'precio_cuatro' => 'nullable|numeric',
                'stock' => 'required|numeric|min:0',
                'descripcion' => 'nullable|string|max:256',
                'costo_compra' => 'required|numeric',
                'vencimiento' => 'nullable|integer',
                'fotografia' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB máximo
                'estado' => 'boolean',
            ]);

            $data = $request->all();

            // Manejo de la fotografía (similar al otro sistema pero mejorado)
            if ($request->hasFile('fotografia')) {
                try {
                    $file = $request->file('fotografia');

                    // Verificar que el archivo es válido
                    if (!$file->isValid()) {
                        \Log::error('Archivo de imagen inválido', ['error' => $file->getError()]);
                        throw new \Exception('El archivo de imagen no es válido');
                    }

                    // Generar nombre de archivo: slug del nombre del artículo + timestamp + extensión
                    // Esto evita colisiones y mantiene nombres legibles
                    $nombreArticulo = $request->input('nombre', 'articulo');
                    $slug = Str::slug($nombreArticulo);
                    $extension = $file->getClientOriginalExtension();
                    $filename = $slug . '_' . time() . '.' . $extension;

                    // Asegurar que el directorio existe
                    $directory = 'articulos';
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }

                    // Guardar la imagen usando Storage (mejor práctica que copy/move)
                    $saved = $file->storeAs($directory, $filename, 'public');

                    if (!$saved) {
                        \Log::error('Error al guardar imagen', ['filename' => $filename]);
                        throw new \Exception('Error al guardar la imagen');
                    }

                    // Guardar solo el nombre del archivo en la BD (no la ruta completa)
                    $data['fotografia'] = $filename;
                    \Log::info('Imagen guardada exitosamente', [
                        'filename' => $filename,
                        'path' => $saved
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error al procesar imagen en store', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file_info' => $request->hasFile('fotografia') ? [
                            'name' => $request->file('fotografia')->getClientOriginalName(),
                            'size' => $request->file('fotografia')->getSize(),
                            'mime' => $request->file('fotografia')->getMimeType()
                        ] : 'No file'
                    ]);
                    // No lanzar excepción, solo registrar el error
                    // La imagen se guardará como null si falla
                    // Asegurar que fotografia no esté en $data si falló
                    unset($data['fotografia']);
                }
            } else {
                // Si no se envía imagen, no agregar el campo (Laravel no lo actualizará)
                \Log::info('No se envió imagen en store', [
                    'has_file' => $request->hasFile('fotografia'),
                    'fotografia_in_data' => isset($data['fotografia'])
                ]);
            }

            $articulo = Articulo::create($data);
            $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

            // Agregar URL completa de la imagen
            $this->addImageUrl($articulo);

            return response()->json($articulo, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el artículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Articulo $articulo)
    {
        $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

        // Agregar URL completa de la imagen
        $this->addImageUrl($articulo);

        return response()->json($articulo);
    }

    public function update(Request $request, Articulo $articulo)
    {
        try {
            // Asegurar que el artículo esté cargado con el código actual desde la BD
            $articulo->refresh();

            // Verificar el código directamente desde la BD para asegurar que tenemos el valor correcto
            $codigoDesdeBD = \DB::table('articulos')->where('id', $articulo->id)->value('codigo');

            // Normalizar: convertir string vacío a null
            if ($codigoDesdeBD === '') {
                $codigoDesdeBD = null;
            }

            // Si hay diferencia, usar el valor de la BD
            if ($codigoDesdeBD !== $articulo->codigo) {
                $articulo->codigo = $codigoDesdeBD;
            }
            // Normalizar código antes de validar: si es string vacío o la palabra "null", convertir a null real
            if ($request->has('codigo')) {
                $codigoValue = $request->codigo;
                if ($codigoValue === '' || $codigoValue === null || $codigoValue === 'null') {
                    $request->merge(['codigo' => null]);
                } else {
                    // Normalizar trim del código
                    $codigoValue = trim((string) $codigoValue);
                    if ($codigoValue === '') {
                        $request->merge(['codigo' => null]);
                    } else {
                        $request->merge(['codigo' => $codigoValue]);
                    }
                }
            }

            // Normalizar strings vacíos a null para campos opcionales
            $allData = $request->all();
            $nullableFields = ['precio_uno', 'precio_dos', 'precio_tres', 'precio_cuatro', 'descripcion', 'vencimiento'];
            foreach ($nullableFields as $field) {
                if (isset($allData[$field]) && $allData[$field] === '') {
                    $request->merge([$field => null]);
                    $allData[$field] = null;
                }
            }

            // Re-obtener los datos normalizados
            $allData = $request->all();

            // Log para debugging
            \Log::info('Datos recibidos en update', [
                'all_data' => $allData,
                'all_data_keys' => array_keys($allData),
                'has_file' => $request->hasFile('fotografia')
            ]);

            // Validación flexible: solo validar campos que vienen en la petición y no son null
            $rules = [];

            if (array_key_exists('categoria_id', $allData) && $allData['categoria_id'] !== null && $allData['categoria_id'] !== '') {
                $rules['categoria_id'] = 'required|integer|exists:categorias,id';
            }
            if (array_key_exists('proveedor_id', $allData) && $allData['proveedor_id'] !== null && $allData['proveedor_id'] !== '') {
                $rules['proveedor_id'] = 'required|integer|exists:proveedores,id';
            }
            if (array_key_exists('medida_id', $allData) && $allData['medida_id'] !== null && $allData['medida_id'] !== '') {
                $rules['medida_id'] = 'required|integer|exists:medidas,id';
            }
            if (array_key_exists('marca_id', $allData) && $allData['marca_id'] !== null && $allData['marca_id'] !== '') {
                $rules['marca_id'] = 'required|integer|exists:marcas,id';
            }
            if (array_key_exists('industria_id', $allData) && $allData['industria_id'] !== null && $allData['industria_id'] !== '') {
                $rules['industria_id'] = 'required|integer|exists:industrias,id';
            }
            if (array_key_exists('codigo', $allData)) {
                // Normalizar el código del request (trim y convertir null/empty a null)
                $codigoRequest = $allData['codigo'];
                if ($codigoRequest !== null && $codigoRequest !== '') {
                    $codigoRequest = trim((string) $codigoRequest);
                    if ($codigoRequest === '') {
                        $codigoRequest = null;
                    }
                }

                // Permitir códigos duplicados - solo validar formato, no unicidad
                $rules['codigo'] = [
                    'nullable',
                    'string',
                    'max:255'
                ];
            }
            if (isset($allData['nombre']) && $allData['nombre'] !== null) {
                $rules['nombre'] = 'required|string|max:255';
            }
            if (isset($allData['unidad_envase'])) {
                $rules['unidad_envase'] = 'nullable|integer';
            }
            if (isset($allData['precio_costo_unid'])) {
                $rules['precio_costo_unid'] = 'nullable|numeric';
            }
            if (isset($allData['precio_costo_paq'])) {
                $rules['precio_costo_paq'] = 'nullable|numeric';
            }
            if (isset($allData['precio_venta'])) {
                $rules['precio_venta'] = 'nullable|numeric';
            }
            if (isset($allData['precio_uno'])) {
                $rules['precio_uno'] = 'nullable|numeric';
            }
            if (isset($allData['precio_dos'])) {
                $rules['precio_dos'] = 'nullable|numeric';
            }
            if (isset($allData['precio_tres'])) {
                $rules['precio_tres'] = 'nullable|numeric';
            }
            if (isset($allData['precio_cuatro'])) {
                $rules['precio_cuatro'] = 'nullable|numeric';
            }
            if (isset($allData['stock'])) {
                $rules['stock'] = 'nullable|numeric|min:0';
            }
            if (isset($allData['descripcion'])) {
                $rules['descripcion'] = 'nullable|string|max:256';
            }
            if (isset($allData['costo_compra'])) {
                $rules['costo_compra'] = 'nullable|numeric';
            }
            if (isset($allData['vencimiento'])) {
                $rules['vencimiento'] = 'nullable|integer';
            }
            if ($request->hasFile('fotografia')) {
                $rules['fotografia'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'; // 10MB máximo
            }
            if (isset($allData['estado'])) {
                // Aceptar boolean, string '0'/'1', o integer 0/1
                $rules['estado'] = 'nullable';
            }

            if (!empty($rules)) {
                $request->validate($rules);
            }

            $data = $request->only([
                'categoria_id',
                'proveedor_id',
                'medida_id',
                'marca_id',
                'industria_id',
                'codigo',
                'nombre',
                'unidad_envase',
                'precio_costo_unid',
                'precio_costo_paq',
                'precio_venta',
                'precio_uno',
                'precio_dos',
                'precio_tres',
                'precio_cuatro',
                'stock',
                'descripcion',
                'costo_compra',
                'vencimiento',
                'estado'
            ]);

            // IMPORTANTE: La normalización de campos se hace después de obtener solo los campos enviados
            // Ver más abajo donde se usa $request->only() para preservar datos del servidor

            // Manejo de la fotografía al actualizar (similar al otro sistema pero mejorado)
            // IMPORTANTE: Procesar la fotografía ANTES del array_filter para que no se elimine
            // IMPORTANTE: Procesar la fotografía ANTES del array_filter para que no se elimine
            if ($request->hasFile('fotografia')) {
                try {
                    $file = $request->file('fotografia');

                    // Verificar que el archivo es válido
                    if (!$file->isValid()) {
                        \Log::error('Archivo de imagen inválido en update', ['error' => $file->getError()]);
                        throw new \Exception('El archivo de imagen no es válido');
                    }

                    // Eliminar imagen anterior si existe
                    if ($articulo->fotografia) {
                        // Si tiene ruta completa (compatibilidad con datos antiguos)
                        if (strpos($articulo->fotografia, '/') !== false) {
                            Storage::disk('public')->delete($articulo->fotografia);
                        } else {
                            // Solo nombre de archivo (nueva lógica)
                            Storage::disk('public')->delete('articulos/' . $articulo->fotografia);
                        }
                    }

                    // Generar nombre de archivo: slug del nombre del artículo + timestamp + extensión
                    $nombreArticulo = $request->input('nombre', $articulo->nombre ?? 'articulo');
                    $slug = Str::slug($nombreArticulo);
                    $extension = $file->getClientOriginalExtension();
                    $filename = $slug . '_' . time() . '.' . $extension;

                    // Asegurar que el directorio existe
                    $directory = 'articulos';
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }

                    // Guardar la imagen usando Storage
                    $saved = $file->storeAs($directory, $filename, 'public');

                    if (!$saved) {
                        \Log::error('Error al guardar imagen en update', ['filename' => $filename]);
                        throw new \Exception('Error al guardar la imagen');
                    }

                    // Guardar solo el nombre del archivo en la BD (no la ruta completa)
                    // Se agregará al array $dataToUpdate más abajo
                    $fotografiaFilename = $filename;
                    \Log::info('Imagen actualizada exitosamente', [
                        'filename' => $filename,
                        'path' => $saved
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error al procesar imagen en update', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // No lanzar excepción, solo registrar el error
                    // La imagen anterior se mantendrá si falla
                }
            }

            // IMPORTANTE: Solo actualizar campos que realmente se enviaron en el request
            // Esto preserva los datos existentes del servidor que no se están actualizando
            // Usar $request->only() para obtener solo los campos que se enviaron explícitamente
            $camposPermitidos = [
                'categoria_id',
                'proveedor_id',
                'medida_id',
                'marca_id',
                'industria_id',
                'codigo',
                'nombre',
                'unidad_envase',
                'precio_costo_unid',
                'precio_costo_paq',
                'precio_venta',
                'precio_uno',
                'precio_dos',
                'precio_tres',
                'precio_cuatro',
                'stock',
                'descripcion',
                'costo_compra',
                'vencimiento',
                'fotografia',
                'estado'
            ];

            // Obtener solo los campos que fueron enviados explícitamente en el request
            $dataToUpdate = $request->only($camposPermitidos);

            // Si se procesó una nueva fotografía, agregarla al array de actualización
            if (isset($fotografiaFilename)) {
                $dataToUpdate['fotografia'] = $fotografiaFilename;
            }

            // Normalizar campos numéricos (solo los que se enviaron)
            $numericFields = ['categoria_id', 'proveedor_id', 'medida_id', 'marca_id', 'industria_id', 'unidad_envase', 'stock', 'vencimiento'];
            foreach ($numericFields as $field) {
                if (isset($dataToUpdate[$field]) && $dataToUpdate[$field] !== null && $dataToUpdate[$field] !== '') {
                    $dataToUpdate[$field] = (int) $dataToUpdate[$field];
                }
            }

            // Normalizar campos decimales (solo los que se enviaron)
            $decimalFields = ['precio_costo_unid', 'precio_costo_paq', 'precio_venta', 'precio_uno', 'precio_dos', 'precio_tres', 'precio_cuatro', 'costo_compra'];
            foreach ($decimalFields as $field) {
                if (isset($dataToUpdate[$field]) && $dataToUpdate[$field] !== null && $dataToUpdate[$field] !== '') {
                    $dataToUpdate[$field] = (float) $dataToUpdate[$field];
                }
            }

            // Normalizar el campo 'estado' si viene como string '0' o '1' (solo si se envió)
            if (isset($dataToUpdate['estado'])) {
                if ($dataToUpdate['estado'] === '0' || $dataToUpdate['estado'] === '1') {
                    $dataToUpdate['estado'] = (bool) $dataToUpdate['estado'];
                } elseif (is_string($dataToUpdate['estado']) && ($dataToUpdate['estado'] === 'true' || $dataToUpdate['estado'] === 'false')) {
                    $dataToUpdate['estado'] = $dataToUpdate['estado'] === 'true';
                }
            }

            // Normalizar el campo 'codigo': convertir string vacío a null (solo si se envió)
            if (isset($dataToUpdate['codigo']) && $dataToUpdate['codigo'] === '') {
                $dataToUpdate['codigo'] = null;
            }

            // Filtrar valores null, pero mantener 0 y false
            $dataToUpdate = array_filter($dataToUpdate, function ($value, $key) {
                // Permitir codigo null si se envió explícitamente
                if ($key === 'codigo') {
                    return true;
                }

                // Permitir fotografia solo si fue procesada (tiene valor)
                if ($key === 'fotografia') {
                    return $value !== null;
                }

                // Para otros campos: solo incluir si tienen valor (no null)
                return $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            // Usar solo los campos que se enviaron para actualizar
            $data = $dataToUpdate;

            $articulo->update($data);
            $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

            // Agregar URL completa de la imagen
            $this->addImageUrl($articulo);

            return response()->json($articulo);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación al actualizar artículo', [
                'articulo_id' => $articulo->id,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'request_keys' => array_keys($request->all()),
                'codigo_actual' => $articulo->codigo,
                'codigo_enviado' => $request->input('codigo'),
                'estado_enviado' => $request->input('estado'),
                'estado_tipo' => gettype($request->input('estado')),
                'codigo_request' => $request->input('codigo')
            ]);

            // Retornar los errores de validación en formato JSON
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            $firstError = '';
            $firstField = '';
            foreach ($e->errors() as $field => $messages) {
                $firstField = $field;
                $firstError = is_array($messages) ? $messages[0] : $messages;
                break;
            }

            return response()->json([
                'message' => $firstError ? "Error en el campo '{$firstField}': {$firstError}" : 'Error de validación',
                'errors' => $e->errors(),
                'request_keys' => array_keys($request->all())
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error al actualizar artículo', [
                'articulo_id' => $articulo->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al actualizar el artículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Articulo $articulo)
    {
        // Eliminar imagen asociada si existe
        if ($articulo->fotografia) {
            // Si tiene ruta completa (compatibilidad con datos antiguos)
            if (strpos($articulo->fotografia, '/') !== false) {
                Storage::disk('public')->delete($articulo->fotografia);
            } else {
                // Solo nombre de archivo (nueva lógica)
                Storage::disk('public')->delete('articulos/' . $articulo->fotografia);
            }
        }
        $articulo->delete();
        return response()->json(null, 204);
    }

    /**
     * Descarga plantilla Excel para importar artículos
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        $excel = app(ExcelService::class);
        return $excel->download(new ArticulosExport(), 'plantilla_articulos.xlsx');
    }

    /**
     * Importa artículos desde un archivo Excel
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        try {
            $import = new ArticulosImport();
            $excel = app(ExcelService::class);
            $excel->import($import, $request->file('file'));

            $errors = [];

            // Obtener errores de validación
            foreach ($import->failures() as $failure) {
                $errors[] = [
                    'fila' => $failure->row(),
                    'atributo' => $failure->attribute(),
                    'errores' => $failure->errors(),
                    'valores' => $failure->values(),
                ];
            }

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $totalRows = $import->getTotalRows();

            return response()->json([
                'message' => 'Importación completada',
                'data' => [
                    'total_filas_procesadas' => $totalRows,
                    'importadas_exitosamente' => $importedCount,
                    'filas_omitidas_duplicados' => $skippedCount,
                    'filas_con_errores' => $import->getErrorCount(),
                    'errores' => $errors,
                ],
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'fila' => $failure->row(),
                    'atributo' => $failure->attribute(),
                    'errores' => $failure->errors(),
                    'valores' => $failure->values(),
                ];
            }

            $importedCount = isset($import) ? $import->getImportedCount() : 0;
            $skippedCount = isset($import) ? $import->getSkippedCount() : count($errors);

            return response()->json([
                'message' => 'Error de validación en el archivo',
                'data' => [
                    'importadas_exitosamente' => $importedCount,
                    'filas_con_errores' => $skippedCount,
                    'errores' => $errors,
                ],
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Error en importación de artículos', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Intentar obtener el conteo de importados incluso si hubo un error
            $importedCount = isset($import) ? $import->getImportedCount() : 0;
            $skippedCount = isset($import) ? $import->getSkippedCount() : 0;

            return response()->json([
                'message' => 'Error al procesar el archivo',
                'data' => [
                    'importadas_exitosamente' => $importedCount,
                    'filas_con_errores' => $skippedCount,
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                ],
            ], 500);
        }
    }
    /**
     * Exporta artículos a Excel
     */
    public function exportExcel()
    {
        return Excel::download(new ArticulosExport, 'articulos.xlsx');
    }

    /**
     * Exporta artículos a PDF
     */
    public function exportPDF()
    {
        $articulos = Articulo::with(['categoria', 'marca', 'medida', 'industria', 'inventarios'])->get();
        $pdf = Pdf::loadView('pdf.articulos', compact('articulos'));
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('articulos.pdf');
    }
    public function toggleStatus(Articulo $articulo)
    {
        $articulo->estado = !$articulo->estado;
        $articulo->save();
        $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);
        $this->addImageUrl($articulo);

        return response()->json([
            'success' => true,
            'message' => $articulo->estado ? 'Artículo activado' : 'Artículo desactivado',
            'data' => $articulo
        ]);
    }
}
