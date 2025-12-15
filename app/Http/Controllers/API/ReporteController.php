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

    /**
     * Reporte de utilidades por sucursal
     */
    public function utilidadesSucursal(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        $fechaDesde = $request->fecha_desde . ' 00:00:00';
        $fechaHasta = $request->fecha_hasta . ' 23:59:59';
        
        // Obtener sucursal_id del request
        $sucursalId = $request->input('sucursal_id');
        
        // Convertir a entero si viene como string
        if ($sucursalId !== null && $sucursalId !== '' && $sucursalId !== 'null' && $sucursalId !== 0) {
            $sucursalId = (int) $sucursalId;
        } else {
            $sucursalId = null;
        }

        // Obtener sucursales (todas o solo la seleccionada)
        $sucursalesQuery = DB::table('sucursales');
        if ($sucursalId !== null && $sucursalId > 0) {
            $sucursalesQuery->where('id', $sucursalId);
        }
        $sucursales = $sucursalesQuery->get();

        $resultado = [];

        foreach ($sucursales as $sucursal) {
            // Obtener ventas de la sucursal (a través de cajas)
            $ventas = DB::table('ventas')
                ->join('cajas', 'ventas.caja_id', '=', 'cajas.id')
                ->join('detalle_ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
                ->join('articulos', 'detalle_ventas.articulo_id', '=', 'articulos.id')
                ->where('cajas.sucursal_id', $sucursal->id)
                ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
                ->select(
                    DB::raw('COUNT(DISTINCT ventas.id) as cantidad_ventas'),
                    DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) as total_ventas'),
                    DB::raw('SUM(detalle_ventas.cantidad * COALESCE(articulos.precio_costo_unid, 0)) as total_costos_ventas')
                )
                ->first();

            // Obtener compras de la sucursal (a través de cajas)
            $compras = DB::table('compras_base')
                ->join('cajas', 'compras_base.caja_id', '=', 'cajas.id')
                ->where('cajas.sucursal_id', $sucursal->id)
                ->whereBetween('compras_base.fecha_hora', [$fechaDesde, $fechaHasta])
                ->select(
                    DB::raw('COUNT(*) as cantidad_compras'),
                    DB::raw('SUM(compras_base.total) as total_compras')
                )
                ->first();

            $totalVentas = (float) ($ventas->total_ventas ?? 0);
            $totalCompras = (float) ($compras->total_compras ?? 0);
            $totalCostosVentas = (float) ($ventas->total_costos_ventas ?? 0);
            
            // Utilidad = Ventas - Costos de ventas - Compras
            $utilidad = $totalVentas - $totalCostosVentas - $totalCompras;
            
            // Margen porcentaje = (Utilidad / Ventas) * 100
            $margenPorcentaje = $totalVentas > 0 ? ($utilidad / $totalVentas) * 100 : 0;

            // Si se filtra por sucursal específica, siempre mostrar aunque no tenga datos
            // Si es reporte general, solo mostrar sucursales con datos
            $debeAgregar = false;
            
            if ($sucursalId !== null && $sucursalId > 0) {
                // Filtrando por sucursal específica: siempre agregar
                $debeAgregar = true;
            } elseif ($totalVentas > 0 || $totalCompras > 0) {
                // Reporte general: solo agregar si tiene datos
                $debeAgregar = true;
            }
            
            if ($debeAgregar) {
                $resultado[] = [
                    'sucursal_id' => $sucursal->id,
                    'sucursal_nombre' => $sucursal->nombre,
                    'total_ventas' => round($totalVentas, 2),
                    'total_compras' => round($totalCompras, 2),
                    'utilidad' => round($utilidad, 2),
                    'margen_porcentaje' => round($margenPorcentaje, 2),
                    'cantidad_ventas' => (int) ($ventas->cantidad_ventas ?? 0),
                    'cantidad_compras' => (int) ($compras->cantidad_compras ?? 0)
                ];
            }
        }

        // Ordenar por utilidad descendente
        usort($resultado, function($a, $b) {
            return $b['utilidad'] <=> $a['utilidad'];
        });

        return response()->json([
            'success' => true,
            'data' => $resultado
        ]);
    }

    /**
     * Exportar utilidades por sucursal a Excel
     */
    public function exportUtilidadesSucursalExcel(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        $response = $this->utilidadesSucursal($request);
        $datosArray = json_decode($response->getContent(), true);

        if (!$datosArray['success']) {
            return response()->json(['success' => false, 'message' => 'Error al generar datos'], 500);
        }

        $fileName = 'utilidades_sucursal_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx';

        // Crear array para exportación (sin encabezados, se agregan con WithHeadings)
        $exportData = [];
        
        foreach ($datosArray['data'] as $item) {
            $exportData[] = [
                $item['sucursal_nombre'],
                $item['total_ventas'],
                $item['total_compras'],
                $item['utilidad'],
                $item['margen_porcentaje'],
                $item['cantidad_ventas'],
                $item['cantidad_compras']
            ];
        }

        return Excel::download(new \App\Exports\UtilidadesSucursalExport($exportData), $fileName);
    }

    /**
     * Exportar utilidades por sucursal a PDF
     */
    public function exportUtilidadesSucursalPDF(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        try {
            $response = $this->utilidadesSucursal($request);
            $datosArray = json_decode($response->getContent(), true);

            if (!$datosArray['success']) {
                return response()->json(['success' => false, 'message' => 'Error al generar datos'], 500);
            }

            $sucursalId = $request->input('sucursal_id');
            $sucursalFiltro = null;
            
            // Obtener nombre de sucursal si se filtró por una específica
            if ($sucursalId) {
                $sucursal = DB::table('sucursales')->where('id', $sucursalId)->first();
                if ($sucursal) {
                    $sucursalFiltro = $sucursal->nombre;
                }
            }

            // Calcular resumen general
            $resumenGeneral = [
                'total_ventas' => array_sum(array_column($datosArray['data'], 'total_ventas')),
                'total_compras' => array_sum(array_column($datosArray['data'], 'total_compras')),
                'utilidad_total' => array_sum(array_column($datosArray['data'], 'utilidad')),
                'margen_promedio' => 0
            ];

            if ($resumenGeneral['total_ventas'] > 0) {
                $resumenGeneral['margen_promedio'] = 
                    ($resumenGeneral['utilidad_total'] / $resumenGeneral['total_ventas']) * 100;
            }

            $datos = [
                'utilidades' => $datosArray['data'],
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'sucursal_filtro' => $sucursalFiltro,
                'resumen_general' => $resumenGeneral
            ];

            $pdf = Pdf::loadView('pdf.utilidades-sucursal', $datos);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('margin-top', 20);
            $pdf->setOption('margin-bottom', 20);
            $pdf->setOption('margin-left', 30);
            $pdf->setOption('margin-right', 30);

            $fileName = 'utilidades_sucursal_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de cajas por sucursal
     */
    public function cajasSucursal(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        $fechaDesde = $request->fecha_desde . ' 00:00:00';
        $fechaHasta = $request->fecha_hasta . ' 23:59:59';
        $sucursalId = $request->input('sucursal_id');
        
        // Log para debug (temporal)
        \Log::info('cajasSucursal - sucursal_id recibido:', ['sucursal_id' => $sucursalId, 'tipo' => gettype($sucursalId)]);
        
        // Convertir a entero si viene como string y no es null
        if ($sucursalId !== null && $sucursalId !== '') {
            $sucursalId = (int) $sucursalId;
        } else {
            $sucursalId = null;
        }

        // Obtener sucursales (todas o solo la seleccionada)
        $sucursalesQuery = DB::table('sucursales');
        if ($sucursalId !== null && $sucursalId > 0) {
            $sucursalesQuery->where('id', $sucursalId);
            \Log::info('Filtrando por sucursal_id:', ['id' => $sucursalId]);
        } else {
            \Log::info('Mostrando todas las sucursales');
        }
        $sucursales = $sucursalesQuery->get();
        
        \Log::info('Sucursales encontradas:', ['count' => $sucursales->count()]);

        $resultado = [];

        foreach ($sucursales as $sucursal) {
            // Obtener todas las cajas de la sucursal en el período
            $cajas = DB::table('cajas')
                ->where('sucursal_id', $sucursal->id)
                ->where(function($query) use ($fechaDesde, $fechaHasta) {
                    $query->whereBetween('fecha_apertura', [$fechaDesde, $fechaHasta])
                          ->orWhereBetween('fecha_cierre', [$fechaDesde, $fechaHasta])
                          ->orWhere(function($q) use ($fechaDesde, $fechaHasta) {
                              $q->where('fecha_apertura', '<=', $fechaHasta)
                                ->where(function($q2) use ($fechaDesde) {
                                    $q2->whereNull('fecha_cierre')
                                       ->orWhere('fecha_cierre', '>=', $fechaDesde);
                                });
                          });
                })
                ->get();

            $cajasIds = $cajas->pluck('id')->toArray();

            // Si se filtra por sucursal específica, mostrar aunque no tenga cajas
            // Si es reporte general, solo mostrar sucursales con cajas
            if (empty($cajasIds)) {
                if ($sucursalId !== null && $sucursalId > 0) {
                    // Mostrar sucursal filtrada aunque no tenga cajas
                    $resultado[] = [
                        'sucursal_id' => $sucursal->id,
                        'sucursal_nombre' => $sucursal->nombre,
                        'total_ventas' => 0,
                        'ventas_contado' => 0,
                        'ventas_credito' => 0,
                        'ventas_qr' => 0,
                        'total_compras' => 0,
                        'compras_contado' => 0,
                        'compras_credito' => 0,
                        'depositos' => 0,
                        'salidas' => 0,
                        'utilidad' => 0,
                        'margen_porcentaje' => 0,
                        'cantidad_cajas' => 0,
                        'cajas_abiertas' => 0,
                        'cajas_cerradas' => 0
                    ];
                }
                continue;
            }

            // Obtener ventas de las cajas
            $ventas = DB::table('ventas')
                ->join('tipo_ventas', 'ventas.tipo_venta_id', '=', 'tipo_ventas.id')
                ->leftJoin('tipo_pagos', 'ventas.tipo_pago_id', '=', 'tipo_pagos.id')
                ->whereIn('ventas.caja_id', $cajasIds)
                ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
                ->select(
                    DB::raw('SUM(ventas.total) as total_ventas'),
                    DB::raw('SUM(CASE WHEN (LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%contado%" OR LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%efectivo%") AND (LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qr%" AND LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_contado'),
                    DB::raw('SUM(CASE WHEN (LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%credito%" OR LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%crédito%") AND (LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qr%" AND LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_credito'),
                    DB::raw('SUM(CASE WHEN (LOWER(tipo_pagos.nombre_tipo_pago) LIKE "%qr%" OR LOWER(tipo_pagos.nombre_tipo_pago) LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_qr')
                )
                ->first();

            // Obtener compras de las cajas
            $compras = DB::table('compras_base')
                ->whereIn('compras_base.caja_id', $cajasIds)
                ->whereBetween('compras_base.fecha_hora', [$fechaDesde, $fechaHasta])
                ->select(
                    DB::raw('SUM(compras_base.total) as total_compras'),
                    DB::raw('SUM(CASE WHEN LOWER(compras_base.tipo_compra) = "contado" THEN compras_base.total ELSE 0 END) as compras_contado'),
                    DB::raw('SUM(CASE WHEN LOWER(compras_base.tipo_compra) IN ("credito", "crédito") THEN compras_base.total ELSE 0 END) as compras_credito')
                )
                ->first();

            // Obtener transacciones de caja
            $transacciones = DB::table('transacciones_cajas')
                ->whereIn('transacciones_cajas.caja_id', $cajasIds)
                ->whereBetween('transacciones_cajas.fecha', [$fechaDesde, $fechaHasta])
                ->select(
                    DB::raw('SUM(CASE WHEN transacciones_cajas.transaccion = "ingreso" THEN transacciones_cajas.importe ELSE 0 END) as depositos'),
                    DB::raw('SUM(CASE WHEN transacciones_cajas.transaccion = "egreso" THEN transacciones_cajas.importe ELSE 0 END) as salidas')
                )
                ->first();

            // Calcular utilidad (ventas - compras)
            $totalVentas = (float) ($ventas->total_ventas ?? 0);
            $totalCompras = (float) ($compras->total_compras ?? 0);
            $utilidad = $totalVentas - $totalCompras;
            
            // Margen porcentaje = (Utilidad / Ventas) * 100
            $margenPorcentaje = $totalVentas > 0 ? ($utilidad / $totalVentas) * 100 : 0;

            // Contar cajas abiertas y cerradas
            $cajasAbiertas = $cajas->whereNull('fecha_cierre')->count();
            $cajasCerradas = $cajas->whereNotNull('fecha_cierre')->count();

            $resultado[] = [
                'sucursal_id' => $sucursal->id,
                'sucursal_nombre' => $sucursal->nombre,
                'total_ventas' => round($totalVentas, 2),
                'ventas_contado' => round((float) ($ventas->ventas_contado ?? 0), 2),
                'ventas_credito' => round((float) ($ventas->ventas_credito ?? 0), 2),
                'ventas_qr' => round((float) ($ventas->ventas_qr ?? 0), 2),
                'total_compras' => round($totalCompras, 2),
                'compras_contado' => round((float) ($compras->compras_contado ?? 0), 2),
                'compras_credito' => round((float) ($compras->compras_credito ?? 0), 2),
                'depositos' => round((float) ($transacciones->depositos ?? 0), 2),
                'salidas' => round((float) ($transacciones->salidas ?? 0), 2),
                'utilidad' => round($utilidad, 2),
                'margen_porcentaje' => round($margenPorcentaje, 2),
                'cantidad_cajas' => $cajas->count(),
                'cajas_abiertas' => $cajasAbiertas,
                'cajas_cerradas' => $cajasCerradas
            ];
        }

        // Ordenar por total de ventas descendente
        usort($resultado, function($a, $b) {
            return $b['total_ventas'] <=> $a['total_ventas'];
        });

        return response()->json([
            'success' => true,
            'data' => $resultado
        ]);
    }

    /**
     * Exportar cajas por sucursal a Excel
     */
    public function exportCajasSucursalExcel(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        $response = $this->cajasSucursal($request);
        $datosArray = json_decode($response->getContent(), true);

        if (!$datosArray['success']) {
            return response()->json(['success' => false, 'message' => 'Error al generar datos'], 500);
        }

        $fileName = 'cajas_sucursal_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx';

        // Crear array para exportación
        $exportData = [];
        
        foreach ($datosArray['data'] as $item) {
            $exportData[] = [
                $item['sucursal_nombre'],
                $item['total_ventas'],
                $item['ventas_contado'],
                $item['ventas_credito'],
                $item['ventas_qr'],
                $item['total_compras'],
                $item['compras_contado'],
                $item['compras_credito'],
                $item['depositos'],
                $item['salidas'],
                $item['utilidad'],
                $item['margen_porcentaje'],
                $item['cantidad_cajas'],
                $item['cajas_abiertas'],
                $item['cajas_cerradas']
            ];
        }

        return Excel::download(new \App\Exports\CajasSucursalExport($exportData), $fileName);
    }

    /**
     * Exportar cajas por sucursal a PDF
     */
    public function exportCajasSucursalPDF(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id'
        ]);

        try {
            $fechaDesde = $request->fecha_desde . ' 00:00:00';
            $fechaHasta = $request->fecha_hasta . ' 23:59:59';
            $sucursalId = $request->input('sucursal_id');
            
            // Log para debug (temporal)
            \Log::info('exportCajasSucursalPDF - sucursal_id recibido:', ['sucursal_id' => $sucursalId, 'tipo' => gettype($sucursalId)]);
            
            // Convertir a entero si viene como string y no es null
            if ($sucursalId !== null && $sucursalId !== '') {
                $sucursalId = (int) $sucursalId;
            } else {
                $sucursalId = null;
            }

            // Obtener sucursales (todas o solo la seleccionada)
            $sucursalesQuery = DB::table('sucursales');
            if ($sucursalId !== null && $sucursalId > 0) {
                $sucursalesQuery->where('id', $sucursalId);
                \Log::info('Filtrando PDF por sucursal_id:', ['id' => $sucursalId]);
            } else {
                \Log::info('Mostrando todas las sucursales en PDF');
            }
            $sucursales = $sucursalesQuery->get();

            $resultado = [];
            $sucursalFiltro = null;

            foreach ($sucursales as $sucursal) {
                if ($sucursalId) {
                    $sucursalFiltro = $sucursal->nombre;
                }

                // Obtener todas las cajas de la sucursal en el período
                $cajas = DB::table('cajas')
                    ->where('sucursal_id', $sucursal->id)
                    ->where(function($query) use ($fechaDesde, $fechaHasta) {
                        $query->whereBetween('fecha_apertura', [$fechaDesde, $fechaHasta])
                              ->orWhereBetween('fecha_cierre', [$fechaDesde, $fechaHasta])
                              ->orWhere(function($q) use ($fechaDesde, $fechaHasta) {
                                  $q->where('fecha_apertura', '<=', $fechaHasta)
                                    ->where(function($q2) use ($fechaDesde) {
                                        $q2->whereNull('fecha_cierre')
                                           ->orWhere('fecha_cierre', '>=', $fechaDesde);
                                    });
                              });
                    })
                    ->get();

                $cajasIds = $cajas->pluck('id')->toArray();

                if (empty($cajasIds)) {
                    continue;
                }

                // Obtener ventas de las cajas
                $ventas = DB::table('ventas')
                    ->join('tipo_ventas', 'ventas.tipo_venta_id', '=', 'tipo_ventas.id')
                    ->leftJoin('tipo_pagos', 'ventas.tipo_pago_id', '=', 'tipo_pagos.id')
                    ->whereIn('ventas.caja_id', $cajasIds)
                    ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
                    ->select(
                        DB::raw('SUM(ventas.total) as total_ventas'),
                        DB::raw('SUM(CASE WHEN (LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%contado%" OR LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%efectivo%") AND (LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qr%" AND LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_contado'),
                        DB::raw('SUM(CASE WHEN (LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%credito%" OR LOWER(tipo_ventas.nombre_tipo_ventas) LIKE "%crédito%") AND (LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qr%" AND LOWER(tipo_pagos.nombre_tipo_pago) NOT LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_credito'),
                        DB::raw('SUM(CASE WHEN (LOWER(tipo_pagos.nombre_tipo_pago) LIKE "%qr%" OR LOWER(tipo_pagos.nombre_tipo_pago) LIKE "%qrcode%") THEN ventas.total ELSE 0 END) as ventas_qr')
                    )
                    ->first();

                // Obtener compras de las cajas
                $compras = DB::table('compras_base')
                    ->whereIn('compras_base.caja_id', $cajasIds)
                    ->whereBetween('compras_base.fecha_hora', [$fechaDesde, $fechaHasta])
                    ->select(
                        DB::raw('SUM(compras_base.total) as total_compras'),
                        DB::raw('SUM(CASE WHEN LOWER(compras_base.tipo_compra) = "contado" THEN compras_base.total ELSE 0 END) as compras_contado'),
                        DB::raw('SUM(CASE WHEN LOWER(compras_base.tipo_compra) IN ("credito", "crédito") THEN compras_base.total ELSE 0 END) as compras_credito')
                    )
                    ->first();

                // Obtener transacciones de caja
                $transacciones = DB::table('transacciones_cajas')
                    ->whereIn('transacciones_cajas.caja_id', $cajasIds)
                    ->whereBetween('transacciones_cajas.fecha', [$fechaDesde, $fechaHasta])
                    ->select(
                        DB::raw('SUM(CASE WHEN transacciones_cajas.transaccion = "ingreso" THEN transacciones_cajas.importe ELSE 0 END) as depositos'),
                        DB::raw('SUM(CASE WHEN transacciones_cajas.transaccion = "egreso" THEN transacciones_cajas.importe ELSE 0 END) as salidas')
                    )
                    ->first();

                // Calcular utilidad (ventas - compras)
                $totalVentas = (float) ($ventas->total_ventas ?? 0);
                $totalCompras = (float) ($compras->total_compras ?? 0);
                $utilidad = $totalVentas - $totalCompras;
                
                // Margen porcentaje = (Utilidad / Ventas) * 100
                $margenPorcentaje = $totalVentas > 0 ? ($utilidad / $totalVentas) * 100 : 0;

                $resultado[] = [
                    'sucursal_nombre' => $sucursal->nombre,
                    'total_ventas' => round($totalVentas, 2),
                    'ventas_contado' => round((float) ($ventas->ventas_contado ?? 0), 2),
                    'ventas_credito' => round((float) ($ventas->ventas_credito ?? 0), 2),
                    'ventas_qr' => round((float) ($ventas->ventas_qr ?? 0), 2),
                    'total_compras' => round($totalCompras, 2),
                    'compras_contado' => round((float) ($compras->compras_contado ?? 0), 2),
                    'compras_credito' => round((float) ($compras->compras_credito ?? 0), 2),
                    'depositos' => round((float) ($transacciones->depositos ?? 0), 2),
                    'salidas' => round((float) ($transacciones->salidas ?? 0), 2),
                    'utilidad' => round($utilidad, 2),
                    'margen_porcentaje' => round($margenPorcentaje, 2),
                    'cantidad_cajas' => $cajas->count()
                ];
            }

            // Calcular resumen general
            $resumenGeneral = [
                'total_ventas' => array_sum(array_column($resultado, 'total_ventas')),
                'total_compras' => array_sum(array_column($resultado, 'total_compras')),
                'utilidad_total' => array_sum(array_column($resultado, 'utilidad')),
                'margen_promedio' => 0
            ];

            if ($resumenGeneral['total_ventas'] > 0) {
                $resumenGeneral['margen_promedio'] = 
                    ($resumenGeneral['utilidad_total'] / $resumenGeneral['total_ventas']) * 100;
            }

            $datos = [
                'cajas' => $resultado,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'sucursal_filtro' => $sucursalFiltro,
                'resumen_general' => $resumenGeneral
            ];

            // Usar el mismo método exacto que funciona en exportVentasPDF
            // Usar el mismo método exacto que funciona en exportVentasPDF
            $pdf = Pdf::loadView('pdf.cajas-sucursal', $datos);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('margin-top', 20);
            $pdf->setOption('margin-bottom', 20);
            $pdf->setOption('margin-left', 30);
            $pdf->setOption('margin-right', 30);

            $fileName = 'cajas_sucursal_' . $request->fecha_desde . '_' . $request->fecha_hasta;
            if ($sucursalId) {
                $fileName .= '_' . $sucursalId;
            }
            $fileName .= '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}

