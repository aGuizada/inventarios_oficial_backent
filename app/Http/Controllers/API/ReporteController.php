<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use App\Exports\VentasReporteExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    protected $reporteService;

    public function __construct(ReporteService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    /**
     * Reporte de ventas por período
     */
    public function ventas(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $datos = $this->reporteService->generarReporteVentas($request->all());

        return response()->json([
            'success' => true,
            'data' => $datos
        ]);
    }

    /**
     * Exportar reporte de ventas a Excel
     */
    public function exportVentasExcel(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        try {
            $datos = $this->reporteService->generarReporteVentas($request->all());
            $fileName = 'reporte_ventas_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx';

            return Excel::download(
                new VentasReporteExport($datos['ventas'], $datos['resumen']),
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
     * Exportar reporte de ventas a PDF
     */
    public function exportVentasPDF(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        try {
            $datos = $this->reporteService->generarReporteVentas($request->all());

            $pdf = Pdf::loadView('pdf.reporte-ventas', $datos);
            $pdf->setPaper('a4', 'landscape');

            $fileName = 'reporte_ventas_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de compras por período
     */
    public function compras(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $datos = $this->reporteService->generarReporteCompras($request->all());

        return response()->json([
            'success' => true,
            'data' => $datos
        ]);
    }

    /**
     * Exportar reporte de compras a Excel
     */
    public function exportComprasExcel(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        try {
            $datos = $this->reporteService->generarReporteCompras($request->all());
            $fileName = 'reporte_compras_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx';

            // Simplificado - mapear manualmente
            $comprasData = collect($datos['compras'])->map(function ($compra) {
                return [
                    'fecha' => $compra->fecha_hora,
                    'proveedor' => $compra->proveedor_id,
                    'total' => $compra->total,
                ];
            });

            return Excel::download(
                new class ($comprasData) implements
                    \Maatwebsite\Excel\Concerns\FromCollection,
                    \Maatwebsite\Excel\Concerns\WithHeadings {

                protected $data;
                public function __construct($data)
                {
                    $this->data = $data;
                }
                public function collection()
                {
                    return $this->data;
                }
                public function headings(): array
                {
                    return ['Fecha', 'Proveedor', 'Total'];
                }
                },
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
     * Reporte de inventario
     */
    public function inventario(Request $request)
    {
        $datos = $this->reporteService->generarReporteInventario($request->all());

        return response()->json([
            'success' => true,
            'data' => $datos
        ]);
    }

    /**
     * Exportar reporte de inventario a Excel
     */
    public function exportInventarioExcel(Request $request)
    {
        try {
            $datos = $this->reporteService->generarReporteInventario($request->all());
            $fileName = 'reporte_inventario_' . date('Y-m-d') . '.xlsx';

            $inventarioData = $datos['inventarios']->map(function ($inv) {
                return [
                    'articulo' => $inv->articulo->nombre ?? 'N/A',
                    'categoria' => $inv->articulo->categoria->nombre ?? 'N/A',
                    'almacen' => $inv->almacen->nombre_almacen ?? 'N/A',
                    'stock' => $inv->saldo_stock,
                    'costo' => $inv->articulo->precio_costo_unid ?? 0,
                    'valor_total' => $inv->saldo_stock * ($inv->articulo->precio_costo_unid ?? 0),
                ];
            });

            return Excel::download(
                new class ($inventarioData) implements
                    \Maatwebsite\Excel\Concerns\FromCollection,
                    \Maatwebsite\Excel\Concerns\WithHeadings {

                protected $data;
                public function __construct($data)
                {
                    $this->data = $data;
                }
                public function collection()
                {
                    return $this->data;
                }
                public function headings(): array
                {
                    return ['Artículo', 'Categoría', 'Almacén', 'Stock', 'Costo Unit.', 'Valor Total'];
                }
                },
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
     * Reporte de créditos
     */
    public function creditos(Request $request)
    {
        $datos = $this->reporteService->generarReporteCreditos($request->all());

        return response()->json([
            'success' => true,
            'data' => $datos
        ]);
    }

    /**
     * Productos más vendidos (legacy endpoint)
     */
    public function productosMasVendidos(Request $request)
    {
        $fechaDesde = $request->input('fecha_desde', now()->subMonth()->format('Y-m-d')) . ' 00:00:00';
        $fechaHasta = $request->input('fecha_hasta', now()->format('Y-m-d')) . ' 23:59:59';
        $limite = $request->input('limite', 20);

        $productos = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('articulos', 'detalle_ventas.articulo_id', '=', 'articulos.id')
            ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
            ->select(
                'articulos.id',
                'articulos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'),
                DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) as total_ventas'),
                DB::raw('COUNT(DISTINCT ventas.id) as num_transacciones'),
                DB::raw('AVG(detalle_ventas.cantidad) as promedio_por_venta')
            )
            ->groupBy('articulos.id', 'articulos.nombre')
            ->orderByDesc('total_ventas')
            ->limit($limite)
            ->get()
            ->map(function ($item) {
                return [
                    'articulo' => [
                        'id' => $item->id,
                        'nombre' => $item->nombre
                    ],
                    'total_cantidad' => $item->total_cantidad,
                    'total_ventas' => round($item->total_ventas, 2),
                    'num_transacciones' => $item->num_transacciones,
                    'promedio_por_venta' => round($item->promedio_por_venta, 2)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $productos
        ]);
    }

    /**
     * Stock bajo (legacy endpoint)
     */
    public function stockBajo(Request $request)
    {
        $almacenId = $request->input('almacen_id');

        $query = DB::table('inventarios')
            ->join('articulos', 'inventarios.articulo_id', '=', 'articulos.id')
            ->join('almacenes', 'inventarios.almacen_id', '=', 'almacenes.id')
            ->where('inventarios.saldo_stock', '<', 10);

        if ($almacenId) {
            $query->where('inventarios.almacen_id', $almacenId);
        }

        $items = $query->select(
            'articulos.id as articulo_id',
            'articulos.nombre',
            'inventarios.saldo_stock as stock_actual',
            'almacenes.nombre_almacen'
        )
            ->get()
            ->map(function ($item) {
                $stockMinimo = 5; // Valor por defecto
                $diferencia = $item->stock_actual - $stockMinimo;
                $estado = $item->stock_actual <= 0 ? 'agotado' :
                    ($item->stock_actual < 5 ? 'critico' : 'bajo');

                return [
                    'articulo' => [
                        'id' => $item->articulo_id,
                        'nombre' => $item->nombre
                    ],
                    'stock_actual' => $item->stock_actual,
                    'stock_minimo' => $stockMinimo,
                    'diferencia' => $diferencia,
                    'estado' => $estado,
                    'sugerencia_reorden' => max(0, $stockMinimo * 2 - $item->stock_actual)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    /**
     * Reporte de utilidad (legacy endpoint)
     */
    public function utilidad(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $fechaDesde = $request->fecha_desde . ' 00:00:00';
        $fechaHasta = $request->fecha_hasta . ' 23:59:59';

        // Calcular utilidad por producto
        $porProducto = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('articulos', 'detalle_ventas.articulo_id', '=', 'articulos.id')
            ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
            ->select(
                'articulos.nombre as articulo',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad_vendida'),
                DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) as total_ventas'),
                DB::raw('SUM(detalle_ventas.cantidad * COALESCE(articulos.precio_costo_unid, 0)) as total_costos'),
                DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) - SUM(detalle_ventas.cantidad * COALESCE(articulos.precio_costo_unid, 0)) as utilidad'),
                DB::raw('((SUM(detalle_ventas.cantidad * detalle_ventas.precio) - SUM(detalle_ventas.cantidad * COALESCE(articulos.precio_costo_unid, 0))) / NULLIF(SUM(detalle_ventas.cantidad * detalle_ventas.precio), 0)) * 100 as margen_porcentaje')
            )
            ->groupBy('articulos.id', 'articulos.nombre')
            ->orderByDesc('utilidad')
            ->get()
            ->map(function ($item) {
                return [
                    'articulo' => $item->articulo,
                    'cantidad_vendida' => $item->cantidad_vendida,
                    'total_ventas' => round($item->total_ventas, 2),
                    'total_costos' => round($item->total_costos, 2),
                    'utilidad' => round($item->utilidad, 2),
                    'margen_porcentaje' => round($item->margen_porcentaje ?? 0, 2)
                ];
            });

        // Calcular totales
        $totalVentas = $porProducto->sum('total_ventas');
        $totalCostos = $porProducto->sum('total_costos');
        $utilidadBruta = $totalVentas - $totalCostos;
        $margenPorcentaje = $totalVentas > 0 ? ($utilidadBruta / $totalVentas) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => [
                    'total_ventas' => round($totalVentas, 2),
                    'total_costos' => round($totalCostos, 2),
                    'utilidad_bruta' => round($utilidadBruta, 2),
                    'margen_porcentaje' => round($margenPorcentaje, 2)
                ],
                'por_producto' => $porProducto
            ]
        ]);
    }
}

