<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelService;
use App\Exports\ProveedoresExport;
use App\Imports\ProveedoresImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ProveedorController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Proveedor::query();

        // Campos buscables: nombre, teléfono, email, NIT, dirección
        $searchableFields = [
            'nombre',
            'telefono',
            'email',
            'nit',
            'direccion'
        ];

        // Aplicar búsqueda
        $query = $this->applySearch($query, $request, $searchableFields);

        // Campos ordenables
        $sortableFields = ['id', 'nombre', 'email', 'telefono', 'created_at'];

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');

        // Aplicar paginación
        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_proveedor' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $proveedor = Proveedor::create($request->all());

        return response()->json($proveedor, 201);
    }

    public function show(Proveedor $proveedor)
    {
        return response()->json($proveedor);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_proveedor' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $proveedor->update($request->all());

        return response()->json($proveedor);
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete();
        return response()->json(null, 204);
    }

    /**
     * Descarga plantilla Excel para importar proveedores
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        $excel = app(ExcelService::class);
        return $excel->download(new ProveedoresExport(), 'plantilla_proveedores.xlsx');
    }

    /**
     * Importa proveedores desde un archivo Excel
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
            $import = new ProveedoresImport();
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
     * Exporta proveedores a Excel
     */
    public function exportExcel()
    {
        return Excel::download(new ProveedoresExport, 'proveedores.xlsx');
    }

    /**
     * Exporta proveedores a PDF
     */
    public function exportPDF()
    {
        $proveedores = Proveedor::all();
        $pdf = Pdf::loadView('pdf.proveedores', compact('proveedores'));
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('proveedores.pdf');
    }
}
