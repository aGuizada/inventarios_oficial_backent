<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\CreditoVenta;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteService
{
    /**
     * Genera reporte de ventas con análisis completo
     */
    public function generarReporteVentas(array $filtros)
    {
        $fechaDesde = $filtros['fecha_desde'] . ' 00:00:00';
        $fechaHasta = $filtros['fecha_hasta'] . ' 23:59:59';

        $query = Venta::whereBetween('fecha_hora', [$fechaDesde, $fechaHasta])
            ->with(['cliente', 'tipoVenta', 'tipoPago', 'user']);

        // Aplicar filtros opcionales
        if (!empty($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }
        if (!empty($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }
        if (!empty($filtros['tipo_venta_id'])) {
            $query->where('tipo_venta_id', $filtros['tipo_venta_id']);
        }

        $ventas = $query->orderBy('fecha_hora', 'desc')->get();

        // Cálculos y análisis
        $totalVentas = $ventas->sum('total');
        $cantidadTransacciones = $ventas->count();
        $ticketPromedio = $cantidadTransacciones > 0 ? $totalVentas / $cantidadTransacciones : 0;

        // Análisis por método de pago
        $metodosPago = $ventas->groupBy('tipo_pago_id')->map(function ($grupo) {
            return [
                'tipo_pago' => $grupo->first()->tipoPago?->nombre_tipo_pago ?? 'N/A',
                'cantidad' => $grupo->count(),
                'total' => round($grupo->sum('total'), 2),
                'porcentaje' => 0 // Se calcula después
            ];
        })->values();

        // Calcular porcentajes
        if ($totalVentas > 0) {
            $metodosPago = $metodosPago->map(function ($metodo) use ($totalVentas) {
                $metodo['porcentaje'] = round(($metodo['total'] / $totalVentas) * 100, 2);
                return $metodo;
            });
        }

        // Ventas por día
        $ventasPorDia = $ventas->groupBy(function ($venta) {
            return Carbon::parse($venta->fecha_hora)->format('Y-m-d');
        })->map(function ($grupo, $fecha) {
            return [
                'fecha' => $fecha,
                'total' => round($grupo->sum('total'), 2),
                'cantidad' => $grupo->count()
            ];
        })->values()->sortBy('fecha')->values();

        // Top 10 productos más vendidos
        $productosTop = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('articulos', 'detalle_ventas.articulo_id', '=', 'articulos.id')
            ->whereBetween('ventas.fecha_hora', [$fechaDesde, $fechaHasta])
            ->select(
                'articulos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad_vendida'),
                DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) as total_ventas')
            )
            ->groupBy('articulos.id', 'articulos.nombre')
            ->orderByDesc('total_ventas')
            ->limit(10)
            ->get();

        return [
            'periodo' => [
                'fecha_desde' => $filtros['fecha_desde'],
                'fecha_hasta' => $filtros['fecha_hasta'],
            ],
            'resumen' => [
                'total_ventas' => round($totalVentas, 2),
                'cantidad_transacciones' => $cantidadTransacciones,
                'ticket_promedio' => round($ticketPromedio, 2),
            ],
            'metodos_pago' => $metodosPago,
            'ventas_por_dia' => $ventasPorDia,
            'productos_top' => $productosTop,
            'ventas' => $ventas
        ];
    }

    /**
     * Genera reporte de compras con análisis completo
     */
    public function generarReporteCompras(array $filtros)
    {
        $fechaDesde = $filtros['fecha_desde'] . ' 00:00:00';
        $fechaHasta = $filtros['fecha_hasta'] . ' 23:59:59';

        $query = DB::table('compras_base')
            ->whereBetween('fecha_hora', [$fechaDesde, $fechaHasta]);

        if (!empty($filtros['proveedor_id'])) {
            $query->where('proveedor_id', $filtros['proveedor_id']);
        }

        $compras = $query->orderBy('fecha_hora', 'desc')->get();

        // Cálculos
        $totalCompras = $compras->sum('total');
        $cantidadCompras = $compras->count();
        $promedioCompra = $cantidadCompras > 0 ? $totalCompras / $cantidadCompras : 0;

        // Compras por día
        $comprasPorDia = $compras->groupBy(function ($compra) {
            return Carbon::parse($compra->fecha_hora)->format('Y-m-d');
        })->map(function ($grupo, $fecha) {
            return [
                'fecha' => $fecha,
                'total' => round($grupo->sum('total'), 2),
                'cantidad' => $grupo->count()
            ];
        })->values()->sortBy('fecha')->values();

        // Top proveedores
        $proveedoresTop = DB::table('compras_base')
            ->join('proveedores', 'compras_base.proveedor_id', '=', 'proveedores.id')
            ->whereBetween('compras_base.fecha_hora', [$fechaDesde, $fechaHasta])
            ->select(
                'proveedores.nombre',
                DB::raw('COUNT(*) as cantidad_compras'),
                DB::raw('SUM(compras_base.total) as total_compras')
            )
            ->groupBy('proveedores.id', 'proveedores.nombre')
            ->orderByDesc('total_compras')
            ->limit(10)
            ->get();

        return [
            'periodo' => [
                'fecha_desde' => $filtros['fecha_desde'],
                'fecha_hasta' => $filtros['fecha_hasta'],
            ],
            'resumen' => [
                'total_compras' => round($totalCompras, 2),
                'cantidad_compras' => $cantidadCompras,
                'promedio_compra' => round($promedioCompra, 2),
            ],
            'compras_por_dia' => $comprasPorDia,
            'proveedores_top' => $proveedoresTop,
            'compras' => $compras
        ];
    }

    /**
     * Genera reporte de inventario
     */
    public function generarReporteInventario(array $filtros = [])
    {
        $query = Inventario::with(['articulo.categoria', 'almacen']);

        if (!empty($filtros['almacen_id'])) {
            $query->where('almacen_id', $filtros['almacen_id']);
        }

        if (!empty($filtros['categoria_id'])) {
            $query->whereHas('articulo', function ($q) use ($filtros) {
                $q->where('categoria_id', $filtros['categoria_id']);
            });
        }

        $inventarios = $query->get();

        // Análisis de stock
        $stockCritico = $inventarios->where('saldo_stock', '<', 10)->count();
        $stockAgotado = $inventarios->where('saldo_stock', '<=', 0)->count();
        $valorTotal = $inventarios->sum(function ($inv) {
            return $inv->saldo_stock * ($inv->articulo->precio_costo_unid ?? 0);
        });

        // Por categoría
        $porCategoria = $inventarios->groupBy(function ($inv) {
            return $inv->articulo->categoria->nombre ?? 'Sin categoría';
        })->map(function ($grupo, $categoria) {
            return [
                'categoria' => $categoria,
                'cantidad_items' => $grupo->count(),
                'stock_total' => $grupo->sum('saldo_stock'),
                'valor_total' => round($grupo->sum(function ($inv) {
                    return $inv->saldo_stock * ($inv->articulo->precio_costo_unid ?? 0);
                }), 2)
            ];
        })->values();

        return [
            'resumen' => [
                'total_items' => $inventarios->count(),
                'stock_critico' => $stockCritico,
                'stock_agotado' => $stockAgotado,
                'valor_total' => round($valorTotal, 2),
            ],
            'por_categoria' => $porCategoria,
            'inventarios' => $inventarios
        ];
    }

    /**
     * Genera reporte de créditos
     */
    public function generarReporteCreditos(array $filtros = [])
    {
        $query = CreditoVenta::with(['venta.cliente']);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['cliente_id'])) {
            $query->whereHas('venta', function ($q) use ($filtros) {
                $q->where('cliente_id', $filtros['cliente_id']);
            });
        }

        $creditos = $query->get();

        // Análisis
        $totalCreditos = $creditos->sum('total');
        $creditosPendientes = $creditos->where('estado', '!=', 'Pagado');
        $montoPendiente = $creditosPendientes->sum('total');
        $creditosVencidos = $creditosPendientes->where('fecha_vencimiento', '<', now())->count();

        // Por estado
        $porEstado = $creditos->groupBy('estado')->map(function ($grupo, $estado) {
            return [
                'estado' => $estado,
                'cantidad' => $grupo->count(),
                'monto_total' => round($grupo->sum('total'), 2)
            ];
        })->values();

        return [
            'resumen' => [
                'total_creditos' => $creditos->count(),
                'monto_total' => round($totalCreditos, 2),
                'creditos_pendientes' => $creditosPendientes->count(),
                'monto_pendiente' => round($montoPendiente, 2),
                'creditos_vencidos' => $creditosVencidos,
            ],
            'por_estado' => $porEstado,
            'creditos' => $creditos
        ];
    }
}
