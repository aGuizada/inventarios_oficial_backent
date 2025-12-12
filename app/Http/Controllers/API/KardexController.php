<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Kardex;
use App\Services\KardexService;
use App\Http\Requests\StoreKardexRequest;
use App\Http\Requests\FilterKardexRequest;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    use HasPagination;

    protected $kardexService;

    public function __construct(KardexService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    /**
     * Lista todos los movimientos de kardex con paginación
     */
    public function index(FilterKardexRequest $request)
    {
        $query = Kardex::with(['articulo', 'almacen', 'usuario']);

        // Filtros opcionales
        if ($request->has('articulo_id')) {
            $query->where('articulo_id', $request->articulo_id);
        }

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $searchableFields = [
            'documento_numero',
            'observaciones',
            'articulo.codigo',
            'articulo.nombre',
            'almacen.nombre_almacen'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'fecha', 'tipo_movimiento'], 'fecha', 'desc');

        return $this->paginateResponse($query, $request, 20, 100);
    }

    /**
     * Obtiene kardex de un artículo específico
     */
    public function porArticulo(Request $request, $articuloId)
    {
        $query = Kardex::with(['almacen', 'usuario'])
            ->where('articulo_id', $articuloId);

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $kardex = $query->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kardex
        ]);
    }

    /**
     * Crea un movimiento manual (ajuste de inventario)
     */
    public function store(StoreKardexRequest $request)
    {
        try {
            $kardex = $this->kardexService->registrarMovimiento($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Ajuste registrado exitosamente',
                'data' => $kardex
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error al crear ajuste de inventario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar ajuste: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene resumen general del kardex con KPIs
     */
    public function getResumen(FilterKardexRequest $request)
    {
        try {
            $filtros = $request->only(['articulo_id', 'almacen_id', 'tipo_movimiento', 'fecha_desde', 'fecha_hasta']);
            $resumen = $this->kardexService->obtenerResumen($filtros);

            return response()->json([
                'success' => true,
                'data' => $resumen
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene kardex valorado (vista con precios)
     */
    public function getKardexValorado(FilterKardexRequest $request)
    {
        try {
            $filtros = $request->only(['articulo_id', 'almacen_id', 'tipo_movimiento', 'fecha_desde', 'fecha_hasta']);
            $perPage = $request->input('per_page', 20);

            $kardexValorado = $this->kardexService->obtenerKardexValorado($filtros, $perPage);

            return response()->json([
                'success' => true,
                'data' => $kardexValorado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener kardex valorado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera reporte detallado por artículo
     */
    public function getReportePorArticulo(Request $request, $articuloId)
    {
        try {
            $filtros = $request->only(['almacen_id', 'fecha_desde', 'fecha_hasta']);
            $reporte = $this->kardexService->generarReportePorArticulo($articuloId, $filtros);

            return response()->json([
                'success' => true,
                'data' => $reporte
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los totales calculados de movimientos de kardex
     */
    public function getTotales(FilterKardexRequest $request)
    {
        $query = Kardex::query();

        // Aplicar los mismos filtros que en index
        if ($request->has('articulo_id')) {
            $query->where('articulo_id', $request->articulo_id);
        }

        if ($request->has('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->has('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        // Calcular totales
        $totalEntradas = $query->sum('cantidad_entrada');
        $totalSalidas = $query->sum('cantidad_salida');
        $totalCostos = $query->sum('costo_total');
        $totalVentas = $query->sum('precio_total');

        return response()->json([
            'total_entradas' => round($totalEntradas, 2),
            'total_salidas' => round($totalSalidas, 2),
            'total_costos' => round($totalCostos, 2),
            'total_ventas' => round($totalVentas, 2)
        ]);
    }

    /**
     * Recalcula saldos del kardex para un artículo
     */
    public function recalcular(Request $request)
    {
        $request->validate([
            'articulo_id' => 'required|exists:articulos,id',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        try {
            $this->kardexService->recalcularKardex($request->articulo_id, $request->almacen_id);

            return response()->json([
                'success' => true,
                'message' => 'Kardex recalculado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular kardex: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un movimiento específico
     */
    public function show($id)
    {
        $kardex = Kardex::with(['articulo', 'almacen', 'usuario', 'compra', 'venta', 'traspaso'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $kardex
        ]);
    }

    /**
     * Exportar kardex a Excel
     */
    public function exportExcel(FilterKardexRequest $request)
    {
        try {
            $query = Kardex::with(['articulo', 'almacen', 'usuario']);

            // Aplicar filtros
            if ($request->has('articulo_id')) {
                $query->where('articulo_id', $request->articulo_id);
            }
            if ($request->has('almacen_id')) {
                $query->where('almacen_id', $request->almacen_id);
            }
            if ($request->has('tipo_movimiento')) {
                $query->where('tipo_movimiento', $request->tipo_movimiento);
            }
            if ($request->has('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }
            if ($request->has('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }

            $kardexMovimientos = $query->orderBy('fecha', 'desc')->get();
            $tipo = $request->input('tipo', 'fisico');

            $fileName = 'kardex_' . ($tipo) . '_' . date('Y-m-d_His') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\KardexExport($kardexMovimientos, $tipo),
                $fileName
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar kardex a PDF
     */
    public function exportPDF(FilterKardexRequest $request)
    {
        try {
            $query = Kardex::with(['articulo', 'almacen', 'usuario']);

            // Aplicar filtros
            if ($request->has('articulo_id')) {
                $query->where('articulo_id', $request->articulo_id);
            }
            if ($request->has('almacen_id')) {
                $query->where('almacen_id', $request->almacen_id);
            }
            if ($request->has('tipo_movimiento')) {
                $query->where('tipo_movimiento', $request->tipo_movimiento);
            }
            if ($request->has('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }
            if ($request->has('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }

            $kardexMovimientos = $query->orderBy('fecha', 'desc')->get();
            $tipo = $request->input('tipo', 'fisico');

            // Calcular totales
            $totales = [
                'total_entradas' => $kardexMovimientos->sum('cantidad_entrada'),
                'total_salidas' => $kardexMovimientos->sum('cantidad_salida'),
                'total_costos' => $kardexMovimientos->sum('costo_total'),
                'total_ventas' => $kardexMovimientos->sum('precio_total'),
            ];

            $data = [
                'kardex' => $kardexMovimientos,
                'tipo' => $tipo,
                'totales' => $totales,
                'fecha_generacion' => now()->format('d/m/Y H:i'),
                'filtros' => [
                    'articulo_id' => $request->articulo_id,
                    'almacen_id' => $request->almacen_id,
                    'tipo_movimiento' => $request->tipo_movimiento,
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ]
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.kardex', $data);
            $pdf->setPaper('a4', 'landscape');

            $fileName = 'kardex_' . ($tipo) . '_' . date('Y-m-d_His') . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}

