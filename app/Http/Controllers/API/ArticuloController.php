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

class ArticuloController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Articulo::with(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

        // Campos buscables: código, nombre, descripción, categoría, marca, proveedor
        $searchableFields = [
            'codigo',
            'nombre',
            'descripcion',
            'categoria.nombre',
            'marca.nombre',
            'proveedor.nombre',
            'industria.nombre'
        ];

        // Aplicar búsqueda
        $query = $this->applySearch($query, $request, $searchableFields);

        // Campos ordenables
        $sortableFields = ['id', 'codigo', 'nombre', 'precio_venta', 'stock', 'created_at'];

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');

        // Aplicar paginación
        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'categoria_id' => 'required|exists:categorias,id',
                'proveedor_id' => 'required|exists:proveedores,id',
                'medida_id' => 'required|exists:medidas,id',
                'marca_id' => 'required|exists:marcas,id',
                'industria_id' => 'required|exists:industrias,id',
                'codigo' => 'required|string|max:255|unique:articulos',
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
                $path = $request->file('fotografia')->store('articulos', 'public');
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
            $request->validate([
                'categoria_id' => 'required|exists:categorias,id',
                'proveedor_id' => 'required|exists:proveedores,id',
                'medida_id' => 'required|exists:medidas,id',
                'marca_id' => 'required|exists:marcas,id',
                'industria_id' => 'required|exists:industrias,id',
                'codigo' => 'required|string|max:255|unique:articulos,codigo,' . $articulo->id,
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
                if ($articulo->fotografia) {
                    Storage::disk('public')->delete($articulo->fotografia);
                }
                $path = $request->file('fotografia')->store('articulos', 'public');
                $data['fotografia'] = $path;
            }

            $articulo->update($data);
            $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

            return response()->json($articulo);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
        $articulos = Articulo::with(['categoria', 'marca', 'medida', 'industria'])->get();
        $pdf = Pdf::loadView('pdf.articulos', compact('articulos'));
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('articulos.pdf');
    }
}
