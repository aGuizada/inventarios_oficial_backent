<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\CompraBase;
use App\Models\Inventario;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\DetalleCompra;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * KPIs Principales del Dashboard
     * Retorna métricas clave del negocio
     */
    public function getKpis()
    {
        $hoy = Carbon::today();
        $mesActual = Carbon::now()->startOfMonth();
        $mesAnterior = Carbon::now()->subMonth()->startOfMonth();

        // === VENTAS ===
        $ventasHoy = Venta::whereDate('fecha_hora', $hoy)->sum('total');
        $ventasMes = Venta::where('fecha_hora', '>=', $mesActual)->sum('total');
        $ventasMesAnterior = Venta::whereBetween('fecha_hora', [
            $mesAnterior,
            $mesAnterior->copy()->endOfMonth()
        ])->sum('total');
        $totalVentas = Venta::sum('total');

        // === INVENTARIO ===
        $productosBajoStock = Inventario::where('saldo_stock', '<', 10)->count();
        $productosAgotados = Inventario::where('saldo_stock', '<=', 0)->count();
        $valorTotalInventario = Inventario::join('articulos', 'inventarios.articulo_id', '=', 'articulos.id')
            ->sum(DB::raw('inventarios.saldo_stock * articulos.precio_costo_unid'));

        // === COMPRAS ===
        $comprasMes = CompraBase::where('fecha_hora', '>=', $mesActual)->sum('total');

        // === CRÉDITOS ===
        $creditosVentaPendientes = DB::table('credito_ventas')
            ->where('estado', '!=', 'Pagado')
            ->count();
        $montoCreditosVentas = DB::table('credito_ventas')
            ->where('estado', '!=', 'Pagado')
            ->sum('total');

        // === TENDENCIAS ===
        $crecimientoVentas = $ventasMesAnterior > 0
            ? (($ventasMes - $ventasMesAnterior) / $ventasMesAnterior) * 100
            : 0;

        // === MARGEN ===
        $margenBruto = $ventasMes > 0 && $comprasMes > 0
            ? (($ventasMes - $comprasMes) / $ventasMes) * 100
            : 0;

        return response()->json([
            // Ventas
            'ventas_hoy' => round($ventasHoy, 2),
            'ventas_mes' => round($ventasMes, 2),
            'ventas_mes_anterior' => round($ventasMesAnterior, 2),
            'total_ventas' => round($totalVentas, 2),
            'crecimiento_ventas' => round($crecimientoVentas, 2),

            // Inventario
            'productos_bajo_stock' => $productosBajoStock,
            'productos_agotados' => $productosAgotados,
            'valor_total_inventario' => round($valorTotalInventario, 2),

            // Compras
            'compras_mes' => round($comprasMes, 2),

            // Créditos
            'creditos_pendientes' => $creditosVentaPendientes,
            'monto_creditos_pendientes' => round($montoCreditosVentas, 2),

            // Análisis
            'margen_bruto' => round($margenBruto, 2)
        ]);
    }

    public function getVentasRecientes()
    {
        $ventas = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago'])
            ->orderBy('fecha_hora', 'desc')
            ->limit(5)
            ->get();

        return response()->json($ventas);
    }

    public function getProductosTop()
    {
        // Productos más vendidos
        $masVendidos = DetalleVenta::select(
            'articulo_id',
            DB::raw('SUM(cantidad) as cantidad_vendida'),
            DB::raw('SUM(cantidad * precio) as total_ventas')
        )
            ->with('articulo')
            ->groupBy('articulo_id')
            ->orderByDesc('cantidad_vendida')
            ->take(5)
            ->get();

        // Productos menos vendidos (con al menos 1 venta)
        $menosVendidos = DetalleVenta::select(
            'articulo_id',
            DB::raw('SUM(cantidad) as cantidad_vendida'),
            DB::raw('SUM(cantidad * precio) as total_ventas')
        )
            ->with('articulo')
            ->groupBy('articulo_id')
            ->having('cantidad_vendida', '>', 0)
            ->orderBy('cantidad_vendida', 'asc')
            ->take(5)
            ->get();

        return response()->json([
            'mas_vendidos' => $masVendidos,
            'menos_vendidos' => $menosVendidos
        ]);
    }

    public function getVentasChart()
    {
        // Tendencia de ventas (últimos 7 días)
        $dias = 7;
        $labels = [];
        $data = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = Carbon::today()->subDays($i);
            $labels[] = $fecha->format('d/m');

            $totalDia = Venta::whereDate('fecha_hora', $fecha)->sum('total');
            $data[] = $totalDia;
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data
        ]);
    }

    public function getInventarioChart()
    {
        // Valor de inventario por categoría
        $data = Inventario::join('articulos', 'inventarios.articulo_id', '=', 'articulos.id')
            ->join('categorias', 'articulos.categoria_id', '=', 'categorias.id')
            ->select(
                'categorias.nombre as categoria',
                DB::raw('SUM(inventarios.saldo_stock * articulos.precio_costo_unid) as valor')
            )
            ->groupBy('categorias.nombre')
            ->orderByDesc('valor')
            ->take(6)
            ->get();

        return response()->json([
            'labels' => $data->pluck('categoria'),
            'data' => $data->pluck('valor')
        ]);
    }

    public function getComparativaChart()
    {
        // Ventas vs Compras (Últimos 6 meses)
        $meses = 6;
        $labels = [];
        $ventasData = [];
        $comprasData = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $fecha = Carbon::today()->subMonths($i)->startOfMonth();
            $labels[] = $fecha->format('M Y'); // Eje: "Dic 2025"

            $ventasMes = Venta::whereYear('fecha_hora', $fecha->year)
                ->whereMonth('fecha_hora', $fecha->month)
                ->sum('total');

            $comprasMes = CompraBase::whereYear('fecha_hora', $fecha->year)
                ->whereMonth('fecha_hora', $fecha->month)
                ->sum('total');

            $ventasData[] = $ventasMes;
            $comprasData[] = $comprasMes;
        }

        return response()->json([
            'labels' => $labels,
            'ventas' => $ventasData,
            'compras' => $comprasData
        ]);
    }

    public function getProveedoresTop()
    {
        // Top 5 Proveedores por volumen de compra
        $data = CompraBase::join('proveedores', 'compras_base.proveedor_id', '=', 'proveedores.id')
            ->select(
                'proveedores.nombre',
                DB::raw('SUM(compras_base.total) as total_comprado')
            )
            ->groupBy('proveedores.id', 'proveedores.nombre')
            ->orderByDesc('total_comprado')
            ->take(5)
            ->get();

        return response()->json([
            'labels' => $data->pluck('nombre'),
            'data' => $data->pluck('total_comprado')
        ]);
    }

    public function getClientesFrecuentes()
    {
        $clientes = Venta::select(
            'cliente_id',
            DB::raw('COUNT(*) as cantidad_ventas'),
            DB::raw('SUM(total) as total_gastado')
        )
            ->with('cliente')
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->orderByDesc('cantidad_ventas')
            ->take(5)
            ->get();

        return response()->json($clientes);
    }

    public function getProductosBajoStock()
    {
        $productos = Inventario::with('articulo')
            ->where('saldo_stock', '<', 10)
            ->orderBy('saldo_stock', 'asc')
            ->take(10)
            ->get();

        return response()->json($productos);
    }

    public function getProductosMasComprados()
    {
        $productos = DetalleCompra::select(
            'articulo_id',
            DB::raw('SUM(cantidad) as cantidad_comprada'),
            DB::raw('SUM(cantidad * precio) as total_compras')
        )
            ->with('articulo')
            ->groupBy('articulo_id')
            ->orderByDesc('cantidad_comprada')
            ->take(5)
            ->get();

        return response()->json($productos);
    }

    public function getTopStock()
    {
        $topStock = Inventario::with('articulo')
            ->orderByDesc('saldo_stock')
            ->take(5)
            ->get();

        return response()->json([
            'labels' => $topStock->map(function ($i) {
                return $i->articulo->nombre_articulo ?? 'Item';
            }),
            'data' => $topStock->pluck('saldo_stock')
        ]);
    }

    /**
     * Alertas Críticas del Sistema
     * Muestra elementos que requieren atención inmediata
     */
    public function getAlertas()
    {
        return response()->json([
            'stock_critico' => Inventario::where('saldo_stock', '<=', 0)->count(),
            'stock_bajo' => Inventario::whereBetween('saldo_stock', [1, 9])->count(),
            'creditos_vencidos' => DB::table('cuotas_credito')
                ->where('estado', '!=', 'Pagado')
                ->where('fecha_pago', '<', Carbon::now())
                ->count(),
            'ventas_hoy' => Venta::whereDate('fecha_hora', Carbon::today())->count(),
            'compras_mes' => CompraBase::where('fecha_hora', '>=', Carbon::now()->startOfMonth())->count()
        ]);
    }

    /**
     * Resumen de Cajas
     * Estado actual de las cajas del negocio
     */
    public function getResumenCajas()
    {
        $cajasAbiertas = DB::table('cajas')->where('estado', 1)->count();
        $cajasCerradas = DB::table('cajas')->where('estado', 0)->count();

        $totalEfectivoGlobal = DB::table('cajas')
            ->where('estado', 1)
            ->sum('saldo_caja');

        return response()->json([
            'cajas_abiertas' => $cajasAbiertas,
            'cajas_cerradas' => $cajasCerradas,
            'total_efectivo' => round($totalEfectivoGlobal, 2)
        ]);
    }

    /**
     * Análisis de Rotación de Inventario
     * Top 10 productos con mejor y peor rotación
     */
    public function getRotacionInventario()
    {
        $diasAnalisis = 30;
        $fechaInicio = Carbon::now()->subDays($diasAnalisis);

        // Productos con mejor rotación (más ventas recientes)
        $mejorRotacion = DetalleVenta::select(
            'articulo_id',
            DB::raw('SUM(cantidad) as total_vendido'),
            DB::raw('COUNT(DISTINCT venta_id) as frecuencia_venta')
        )
            ->whereHas('venta', function ($q) use ($fechaInicio) {
                $q->where('fecha_hora', '>=', $fechaInicio);
            })
            ->with('articulo')
            ->groupBy('articulo_id')
            ->orderByDesc('total_vendido')
            ->take(10)
            ->get();

        // Productos sin movimiento reciente (simplificado)
        // Obtener IDs de artículos con ventas recientes
        $articulosConVentas = DetalleVenta::whereHas('venta', function ($q) use ($fechaInicio) {
            $q->where('fecha_hora', '>=', $fechaInicio);
        })
            ->pluck('articulo_id')
            ->unique();

        // Productos en inventario sin ventas recientes
        $sinMovimiento = Inventario::with('articulo')
            ->whereNotIn('articulo_id', $articulosConVentas)
            ->where('saldo_stock', '>', 0)
            ->orderByDesc('saldo_stock')
            ->take(10)
            ->get();

        return response()->json([
            'mejor_rotacion' => $mejorRotacion,
            'sin_movimiento' => $sinMovimiento,
            'dias_analisis' => $diasAnalisis
        ]);
    }
}
