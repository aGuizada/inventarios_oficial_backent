<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelService;
use App\Exports\ArticulosExport;
use App\Imports\ArticulosImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ArticuloController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        // Cargar TODOS los artículos sin filtrar por estado (mostrar todos sin excepción)
        $query = Articulo::with(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

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
        return $this->paginateResponse($query, $request, 15, 999999);
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
                    'max:255',
                    Rule::unique('articulos', 'codigo')->whereNotNull('codigo')
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
                'stock' => 'required|integer',
                'descripcion' => 'nullable|string|max:256',
                'costo_compra' => 'required|numeric',
                'vencimiento' => 'nullable|integer',
                'fotografia' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'estado' => 'boolean',
            ]);

            $data = $request->all();

            if ($request->hasFile('fotografia')) {
                $file = $request->file('fotografia');

                // Optimizar imagen con Intervention Image
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file);

                // Redimensionar para cards (300px es el mínimo recomendado para nitidez básica)
                if ($image->width() > 300) {
                    $image->scale(width: 300);
                }

                // Convertir a WebP con compresión alta (calidad 50)
                $encoded = $image->toWebp(20);

                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . time() . '.webp';
                $path = 'articulos/' . $filename;

                Storage::disk('public')->put($path, $encoded);
                $data['fotografia'] = $path;
            }

            $articulo = Articulo::create($data);
            $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

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
        return response()->json($articulo);
    }

    public function update(Request $request, Articulo $articulo)
    {
        try {
            // Normalizar código antes de validar: si es string vacío o la palabra "null", convertir a null real
            if ($request->has('codigo') && ($request->codigo === '' || $request->codigo === null || $request->codigo === 'null')) {
                $request->merge(['codigo' => null]);
            }

            // Obtener todos los datos del request
            $allData = $request->all();

            // Validación flexible: solo validar campos que vienen en la petición y no son null
            $rules = [];

            if (isset($allData['categoria_id']) && $allData['categoria_id'] !== null && $allData['categoria_id'] !== '') {
                $rules['categoria_id'] = 'required|integer|exists:categorias,id';
            }
            if (isset($allData['proveedor_id']) && $allData['proveedor_id'] !== null && $allData['proveedor_id'] !== '') {
                $rules['proveedor_id'] = 'required|integer|exists:proveedores,id';
            }
            if (isset($allData['medida_id']) && $allData['medida_id'] !== null && $allData['medida_id'] !== '') {
                $rules['medida_id'] = 'required|integer|exists:medidas,id';
            }
            if (isset($allData['marca_id']) && $allData['marca_id'] !== null && $allData['marca_id'] !== '') {
                $rules['marca_id'] = 'required|integer|exists:marcas,id';
            }
            if (isset($allData['industria_id']) && $allData['industria_id'] !== null && $allData['industria_id'] !== '') {
                $rules['industria_id'] = 'required|integer|exists:industrias,id';
            }
            if (array_key_exists('codigo', $allData)) {
                $rules['codigo'] = [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('articulos', 'codigo')->ignore($articulo->id)->whereNotNull('codigo')
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
                $rules['stock'] = 'nullable|integer';
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
                $rules['fotografia'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
            }
            if (isset($allData['estado'])) {
                $rules['estado'] = 'nullable|boolean';
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

            // Convertir valores numéricos a los tipos correctos
            $numericFields = [
                'categoria_id',
                'proveedor_id',
                'medida_id',
                'marca_id',
                'industria_id',
                'unidad_envase',
                'stock',
                'vencimiento'
            ];
            foreach ($numericFields as $field) {
                if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                    $data[$field] = (int) $data[$field];
                }
            }

            $decimalFields = [
                'precio_costo_unid',
                'precio_costo_paq',
                'precio_venta',
                'precio_uno',
                'precio_dos',
                'precio_tres',
                'precio_cuatro',
                'costo_compra'
            ];
            foreach ($decimalFields as $field) {
                if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                    $data[$field] = (float) $data[$field];
                }
            }

            // Filtrar valores null para no sobrescribir con null, pero mantener 0 y false
            // EXCEPCIÓN: Permitir null para 'codigo' si se envió explícitamente
            $data = array_filter($data, function ($value, $key) use ($allData) {
                if ($key === 'codigo' && array_key_exists('codigo', $allData)) {
                    return true;
                }
                return $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            if ($request->hasFile('fotografia')) {
                if ($articulo->fotografia) {
                    Storage::disk('public')->delete($articulo->fotografia);
                }

                $file = $request->file('fotografia');
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file);

                if ($image->width() > 300) {
                    $image->scale(width: 300);
                }

                $encoded = $image->toWebp(20);
                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . time() . '.webp';
                $path = 'articulos/' . $filename;

                Storage::disk('public')->put($path, $encoded);
                $data['fotografia'] = $path;
            }

            $articulo->update($data);
            $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

            return response()->json($articulo);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación al actualizar artículo', [
                'articulo_id' => $articulo->id,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'request_keys' => array_keys($request->all())
            ]);
            return response()->json([
                'message' => 'Error de validación',
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
        if ($articulo->fotografia) {
            Storage::disk('public')->delete($articulo->fotografia);
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

            return response()->json([
                'message' => 'Importación completada',
                'data' => [
                    'total_procesadas' => $importedCount + $skippedCount,
                    'importadas_exitosamente' => $importedCount,
                    'filas_con_errores' => $skippedCount,
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

            return response()->json([
                'message' => 'Error de validación en el archivo',
                'errors' => $errors,
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo',
                'error' => $e->getMessage(),
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
}
