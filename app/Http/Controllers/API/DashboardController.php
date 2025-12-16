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
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Resources\ArticuloUtilidadResource;
use App\Http\Resources\DashboardKpisResource;


class DashboardController extends Controller
{
    /**
     * KPIs Principales del Dashboard
     * Retorna métricas clave del negocio
     */
    public function getKpis()
    {
        // Cachear KPIs por 3 minutos para evitar 12+ queries en cada request
        return Cache::remember('dashboard.kpis', 180, function () {
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
        });
    }

    public function getVentasRecientes()
    {
        // Cachear ventas recientes por 2 minutos
        return Cache::remember('dashboard.ventas_recientes', 120, function () {
            $ventas = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago'])
                ->orderBy('fecha_hora', 'desc')
                ->limit(5)
                ->get();

            return response()->json($ventas);
        });
    }

    public function getProductosTop()
    {
        // Cachear productos top por 5 minutos
        return Cache::remember('dashboard.productos_top', 300, function () {
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
        });
    }

    public function getVentasChart()
    {
        // Cachear gráfica de ventas por 5 minutos
        return Cache::remember('dashboard.ventas_chart', 300, function () {
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
        });
    }

    public function getInventarioChart()
    {
        // Cachear gráfica de inventario por 10 minutos
        return Cache::remember('dashboard.inventario_chart', 600, function () {
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
        });
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

    /**
     * KPIs del Dashboard con Filtros de Fecha
     * Permite filtrar métricas por rangos de fecha personalizados
     */
    public function getKpisFiltrados(DashboardFilterRequest $request)
    {
        // Obtener fechas de inicio y fin
        $fechas = $this->obtenerRangoFechas($request);
        $fechaInicio = $fechas['inicio'];
        $fechaFin = $fechas['fin'];
        $sucursalId = $request->sucursal_id;

        // === VENTAS ===
        $ventasHoyQuery = Venta::whereDate('fecha_hora', Carbon::today());
        $this->aplicarFiltroSucursal($ventasHoyQuery, $sucursalId);
        $ventasHoy = $ventasHoyQuery->sum('total');

        $ventasPeriodoQuery = Venta::whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
        $this->aplicarFiltroSucursal($ventasPeriodoQuery, $sucursalId);
        $ventasPeriodo = $ventasPeriodoQuery->sum('total');

        $totalVentasQuery = Venta::query();
        $this->aplicarFiltroSucursal($totalVentasQuery, $sucursalId);
        $totalVentas = $totalVentasQuery->sum('total');

        // === INVENTARIO (no se filtra por fecha ni sucursal directamente) ===
        $productosBajoStock = Inventario::where('saldo_stock', '<', 10);
        if ($sucursalId) {
            $productosBajoStock->whereHas('almacen', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            });
        }
        $productosBajoStock = $productosBajoStock->count();

        $productosAgotados = Inventario::where('saldo_stock', '<=', 0);
        if ($sucursalId) {
            $productosAgotados->whereHas('almacen', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            });
        }
        $productosAgotados = $productosAgotados->count();

        $valorTotalInventario = Inventario::join('articulos', 'inventarios.articulo_id', '=', 'articulos.id');
        if ($sucursalId) {
            $valorTotalInventario->join('almacenes', 'inventarios.almacen_id', '=', 'almacenes.id')
                ->where('almacenes.sucursal_id', $sucursalId);
        }
        $valorTotalInventario = $valorTotalInventario->sum(DB::raw('inventarios.saldo_stock * articulos.precio_costo_unid'));

        // === COMPRAS ===
        $comprasPeriodoQuery = CompraBase::whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
        $this->aplicarFiltroSucursalCompras($comprasPeriodoQuery, $sucursalId);
        $comprasPeriodo = $comprasPeriodoQuery->sum('total');

        // === CRÉDITOS ===
        $creditosVentaPendientes = DB::table('credito_ventas')
            ->where('estado', '!=', 'Pagado')
            ->count();
        $montoCreditosVentas = DB::table('credito_ventas')
            ->where('estado', '!=', 'Pagado')
            ->sum('total');

        // === MARGEN ===
        $margenBruto = $ventasPeriodo > 0 && $comprasPeriodo > 0
            ? (($ventasPeriodo - $comprasPeriodo) / $ventasPeriodo) * 100
            : 0;

        $kpis = [
            // Ventas
            'ventas_hoy' => round($ventasHoy, 2),
            'ventas_mes' => round($ventasPeriodo, 2),
            'ventas_mes_anterior' => 0,
            'total_ventas' => round($totalVentas, 2),
            'crecimiento_ventas' => 0,

            // Inventario
            'productos_bajo_stock' => $productosBajoStock,
            'productos_agotados' => $productosAgotados,
            'valor_total_inventario' => round($valorTotalInventario, 2),

            // Compras
            'compras_mes' => round($comprasPeriodo, 2),

            // Créditos
            'creditos_pendientes' => $creditosVentaPendientes,
            'monto_creditos_pendientes' => round($montoCreditosVentas, 2),

            // Análisis
            'margen_bruto' => round($margenBruto, 2),

            // Filtros aplicados
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'periodo' => 'personalizado',
        ];

        return new DashboardKpisResource((object) $kpis);
    }

    /**
     * Utilidad/Ganancia por Artículo
     * Calcula y retorna el análisis de rentabilidad de cada artículo
     */
    public function getUtilidadArticulos(DashboardFilterRequest $request)
    {
        // Obtener rangos de fecha
        $fechas = $this->obtenerRangoFechas($request);
        $fechaInicio = $fechas['inicio'];
        $fechaFin = $fechas['fin'];
        $sucursalId = $request->sucursal_id;

        // Query principal: Obtener ventas por artículo con cálculos de utilidad
        $query = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('articulos', 'detalle_ventas.articulo_id', '=', 'articulos.id')
            ->whereBetween('ventas.fecha_hora', [$fechaInicio, $fechaFin]);

        // Aplicar filtro de sucursal si está presente
        if ($sucursalId) {
            $query->join('cajas', 'ventas.caja_id', '=', 'cajas.id')
                ->where('cajas.sucursal_id', $sucursalId);
        }

        $utilidades = $query->select(
            'articulos.id as articulo_id',
            'articulos.codigo',
            'articulos.nombre',
            DB::raw('SUM(detalle_ventas.cantidad) as cantidad_vendida'),
            DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) as total_ventas'),
            DB::raw('SUM(detalle_ventas.cantidad * articulos.precio_costo_unid) as costo_total'),
            DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio) - SUM(detalle_ventas.cantidad * articulos.precio_costo_unid) as utilidad_bruta'),
            DB::raw('CASE 
                    WHEN SUM(detalle_ventas.cantidad * detalle_ventas.precio) > 0 
                    THEN ((SUM(detalle_ventas.cantidad * detalle_ventas.precio) - SUM(detalle_ventas.cantidad * articulos.precio_costo_unid)) / SUM(detalle_ventas.cantidad * detalle_ventas.precio)) * 100 
                    ELSE 0 
                END as margen_porcentaje')
        )
            ->groupBy('articulos.id', 'articulos.codigo', 'articulos.nombre')
            ->orderByDesc('utilidad_bruta')
            ->get();

        return ArticuloUtilidadResource::collection($utilidades);
    }

    /**
     * Gráfica de Ventas con Filtro de Fecha
     */
    public function getVentasChartFiltrado(DashboardFilterRequest $request)
    {
        $fechas = $this->obtenerRangoFechas($request);
        $fechaInicio = $fechas['inicio'];
        $fechaFin = $fechas['fin'];
        $sucursalId = $request->sucursal_id;

        $labels = [];
        $data = [];

        $diasDiferencia = $fechaInicio->diffInDays($fechaFin) + 1;

        // Si el rango es mayor a 31 días, agrupar por mes
        if ($diasDiferencia > 31) {
            $meses = [];
            $mesActual = $fechaInicio->copy()->startOfMonth();
            $mesFinal = $fechaFin->copy()->startOfMonth();

            while ($mesActual <= $mesFinal) {
                $meses[] = $mesActual->copy();
                $mesActual->addMonth();
            }

            foreach ($meses as $mes) {
                $labels[] = $mes->format('M Y');

                $query = Venta::whereYear('fecha_hora', $mes->year)
                    ->whereMonth('fecha_hora', $mes->month);

                $this->aplicarFiltroSucursal($query, $sucursalId);
                $totalMes = $query->sum('total');

                $data[] = $totalMes;
            }
        } else {
            // Agrupar por día
            for ($i = 0; $i < $diasDiferencia; $i++) {
                $fecha = $fechaInicio->copy()->addDays($i);
                $labels[] = $fecha->format('d/m');

                $query = Venta::whereDate('fecha_hora', $fecha);
                $this->aplicarFiltroSucursal($query, $sucursalId);
                $totalDia = $query->sum('total');

                $data[] = $totalDia;
            }
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'periodo' => [
                'inicio' => $fechaInicio->toDateString(),
                'fin' => $fechaFin->toDateString(),
            ],
        ]);
    }


    /**
     * Obtener lista de sucursales para filtros
     */
    public function getSucursales()
    {
        return \App\Models\Sucursal::select('id', 'nombre')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Método privado para obtener rango de fechas desde el request
     */
    private function obtenerRangoFechas(DashboardFilterRequest $request): array
    {
        $fechaInicio = null;
        $fechaFin = null;

        // Modo 1: Rango de fechas específico
        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();
        }
        // Modo 2: Año, mes, y opcionalmente día
        elseif ($request->has('year')) {
            $year = $request->year;
            $month = $request->month ?? null;
            $day = $request->day ?? null;

            if ($day && $month) {
                // Día específico
                $fechaInicio = Carbon::createFromDate($year, $month, $day)->startOfDay();
                $fechaFin = Carbon::createFromDate($year, $month, $day)->endOfDay();
            } elseif ($month) {
                // Mes completo
                $fechaInicio = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $fechaFin = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            } else {
                // Año completo
                $fechaInicio = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $fechaFin = Carbon::createFromDate($year, 12, 31)->endOfYear();
            }
        }
        // Por defecto: último mes
        else {
            $fechaInicio = Carbon::now()->startOfMonth();
            $fechaFin = Carbon::now()->endOfDay();
        }

        return [
            'inicio' => $fechaInicio,
            'fin' => $fechaFin,
        ];
    }

    /**
     * Método privado para aplicar filtro de sucursal a queries de ventas
     * Filtra por sucursal a través de la relación: ventas -> caja -> sucursal
     */
    private function aplicarFiltroSucursal($query, $sucursalId)
    {
        if ($sucursalId) {
            $query->whereHas('caja', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            });
        }
        return $query;
    }

    /**
     * Método privado para aplicar filtro de sucursal a queries de compras
     * Filtra por sucursal a través de la relación: compras_base -> caja -> sucursal
     */
    private function aplicarFiltroSucursalCompras($query, $sucursalId)
    {
        if ($sucursalId) {
            $query->whereHas('caja', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            });
        }
        return $query;
    }
}

