<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Inventario;
use Illuminate\Http\Request;
use App\Exports\InventariosExport;
use App\Exports\InventarioTemplateExport;
use App\Imports\InventarioImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class InventarioController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Inventario::with(['almacen', 'articulo']);

        $searchableFields = [
            'id',
            'cantidad',
            'saldo_stock',
            'ubicacion',
            'articulo.codigo',
            'articulo.nombre',
            'almacen.nombre_almacen'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'cantidad', 'saldo_stock', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'articulo_id' => 'required|exists:articulos,id',
            'cantidad' => 'required|integer',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $inventario = Inventario::create($request->all());

        return response()->json($inventario, 201);
    }

    public function show(Inventario $inventario)
    {
        $inventario->load(['almacen', 'articulo']);
        return response()->json($inventario);
    }

    public function update(Request $request, Inventario $inventario)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'articulo_id' => 'required|exists:articulos,id',
            'cantidad' => 'required|integer',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $inventario->update($request->all());

        return response()->json($inventario);
    }

    public function destroy(Inventario $inventario)
    {
        $inventario->delete();
        return response()->json(null, 204);
    }

    /**
     * Vista de inventario agrupado por ítem (artículo)
     * Suma las cantidades totales por artículo
     */
    public function porItem(Request $request)
    {
        $inventarios = Inventario::with(['articulo', 'almacen'])
            ->select('articulo_id')
            ->selectRaw('SUM(cantidad) as total_stock')
            ->selectRaw('SUM(saldo_stock) as total_saldo')
            ->groupBy('articulo_id')
            ->get();

        // Cargar información del artículo y almacenes para cada grupo
        $resultado = $inventarios->map(function ($inv) {
            $articulo = $inv->articulo;

            // Obtener almacenes donde hay stock de este artículo
            $almacenesConStock = Inventario::where('articulo_id', $inv->articulo_id)
                ->with('almacen')
                ->get()
                ->map(function ($item) {
                    return [
                        'almacen' => $item->almacen->nombre_almacen ?? 'Sin almacén',
                        'cantidad' => $item->cantidad,
                        'saldo_stock' => $item->saldo_stock
                    ];
                });

            return [
                'articulo' => $articulo,
                'total_stock' => $inv->total_stock,
                'total_saldo' => $inv->total_saldo,
                'almacenes' => $almacenesConStock
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $resultado
        ]);
    }

    /**
     * Vista de inventario por lotes (detallado)
     * Muestra cada registro individual como un lote
     */
    public function porLotes(Request $request)
    {
        $query = Inventario::with(['almacen', 'articulo'])
            ->orderBy('created_at', 'desc');

        // Filtro opcional por artículo
        if ($request->has('articulo_id')) {
            $query->where('articulo_id', $request->articulo_id);
        }

        // Filtro opcional por almacén
        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        $searchableFields = [
            'id',
            'cantidad',
            'saldo_stock',
            'articulo.codigo',
            'articulo.nombre',
            'almacen.nombre_almacen'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'cantidad', 'saldo_stock', 'created_at'], 'created_at', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }
    /**
     * Descarga plantilla de importación de inventario
     */
    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new InventarioTemplateExport, 'plantilla_inventario.xlsx');
    }

    /**
     * Importa inventario desde un archivo Excel
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $import = new InventarioImport();
        try {
            Excel::import($import, $request->file('file'));
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
                'errores' => $errors
            ], 422);
        }

        // Si usa SkipsFailures, los errores no lanzan excepción, se recogen aquí
        $failures = $import->failures();
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
            'message' => count($errors) > 0 ? 'Importación completada con algunos errores' : 'Importación completada exitosamente',
            'importadas_exitosamente' => $import->getImportedCount(),
            'filas_con_errores' => $import->getSkippedCount(),
            'errores' => $errors
        ]);
    }

    /**
     * Exporta inventario a Excel
     */
    public function exportPDF()
    {
        $inventarios = Inventario::with(['articulo', 'almacen'])->get();
        $pdf = Pdf::loadView('pdf.inventarios', compact('inventarios'));
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('inventario.pdf');
    }
}
