<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AlmacenController;
use App\Http\Controllers\API\ArqueoCajaController;
use App\Http\Controllers\API\ArticuloController;
use App\Http\Controllers\API\CajaController;
use App\Http\Controllers\API\CategoriaController;
use App\Http\Controllers\API\ClienteController;
use App\Http\Controllers\API\CompraController;
use App\Http\Controllers\API\CompraCuotaController;
use App\Http\Controllers\API\ConfiguracionTrabajoController;
use App\Http\Controllers\API\ConteoFisicoController;
use App\Http\Controllers\API\CotizacionController;
use App\Http\Controllers\API\CreditoVentaController;
use App\Http\Controllers\API\CuotaCreditoController;
use App\Http\Controllers\API\DevolucionController;
use App\Http\Controllers\API\EmpresaController;
use App\Http\Controllers\API\IndustriaController;
use App\Http\Controllers\API\InventarioController;
use App\Http\Controllers\API\KardexController;
use App\Http\Controllers\API\MarcaController;
use App\Http\Controllers\API\MedidaController;
use App\Http\Controllers\API\MonedaController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PrecioController;
use App\Http\Controllers\API\ProveedorController;
use App\Http\Controllers\API\ReporteController;
use App\Http\Controllers\API\RolController;
use App\Http\Controllers\API\SucursalController;
use App\Http\Controllers\API\TipoPagoController;
use App\Http\Controllers\API\TipoVentaController;
use App\Http\Controllers\API\TransaccionCajaController;
use App\Http\Controllers\API\TraspasoController;
use App\Http\Controllers\API\VentaController;
use App\Http\Controllers\API\DashboardController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Ruta pública para servir imágenes de artículos (sin necesidad de storage link)
Route::get('articulos/imagen/{filename}', [ArticuloController::class, 'serveImage'])
    ->where('filename', '.*');

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Usuarios
    Route::get('users/export-excel', [UserController::class, 'exportExcel']);
    Route::get('users/export-pdf', [UserController::class, 'exportPDF']);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Almacenes
    Route::patch('almacenes/{almacen}/toggle-status', [AlmacenController::class, 'toggleStatus']);
    Route::apiResource('almacenes', AlmacenController::class);

    // Arqueos de Caja
    Route::apiResource('arqueos-caja', ArqueoCajaController::class);

    // Artículos
    Route::get('articulos/download-template', [ArticuloController::class, 'downloadTemplate']);
    Route::post('articulos/import', [ArticuloController::class, 'import']);
    Route::get('articulos/export-excel', [ArticuloController::class, 'exportExcel']);
    Route::get('articulos/export-pdf', [ArticuloController::class, 'exportPDF']);
    Route::patch('articulos/{articulo}/toggle-status', [ArticuloController::class, 'toggleStatus']);
    Route::apiResource('articulos', ArticuloController::class);

    // Cajas
    Route::get('cajas/{id}/details', [CajaController::class, 'getCajaDetails']);
    Route::get('cajas/calcular-totales', [CajaController::class, 'calcularTotalesCajas']);
    Route::apiResource('cajas', CajaController::class);

    // Categorías
    Route::patch('categorias/{id}/toggle-status', [CategoriaController::class, 'toggleStatus']);
    Route::apiResource('categorias', CategoriaController::class);

    // Clientes
    Route::get('clientes/export-excel', [ClienteController::class, 'exportExcel']);
    Route::get('clientes/export-pdf', [ClienteController::class, 'exportPDF']);
    Route::patch('clientes/{cliente}/toggle-status', [ClienteController::class, 'toggleStatus']);
    Route::apiResource('clientes', ClienteController::class);

    // Compras
    Route::apiResource('compras', CompraController::class);

    // Cuotas de Compra
    Route::apiResource('compra-cuotas', CompraCuotaController::class);
    Route::get('compra-cuotas/compra-credito/{compraCreditoId}/details', [CompraCuotaController::class, 'getByCompraCreditoWithDetails']);
    Route::post('compra-cuotas/{id}/pagar', [CompraCuotaController::class, 'pagarCuota']);
    Route::get('compra-cuotas/compra-credito/{compraCreditoId}', [CompraCuotaController::class, 'getByCompraCredito']);

    // Configuración de Trabajo
    Route::apiResource('configuracion-trabajo', ConfiguracionTrabajoController::class);

    // Cotizaciones
    Route::get('cotizaciones/{id}/proforma-pdf', [CotizacionController::class, 'generarProformaPDF']);
    Route::apiResource('cotizaciones', CotizacionController::class);

    // Créditos de Venta
    Route::get('creditos-venta/{id}/details', [CreditoVentaController::class, 'getDetails']);
    Route::apiResource('creditos-venta', CreditoVentaController::class);

    // Cuotas de Crédito
    Route::get('cuotas-credito/credito/{creditoId}', [CuotaCreditoController::class, 'getByCredito']);
    Route::post('cuotas-credito/{id}/pagar', [CuotaCreditoController::class, 'pagarCuota']);
    Route::apiResource('cuotas-credito', CuotaCreditoController::class);

    // Empresas
    Route::apiResource('empresas', EmpresaController::class);

    // Industrias
    Route::patch('industrias/{industria}/toggle-status', [IndustriaController::class, 'toggleStatus']);
    Route::apiResource('industrias', IndustriaController::class);

    // Kardex
    Route::get('kardex/export-excel', [KardexController::class, 'exportExcel']);
    Route::get('kardex/export-pdf', [KardexController::class, 'exportPDF']);
    Route::get('kardex/resumen', [KardexController::class, 'getResumen']);
    Route::get('kardex/valorado', [KardexController::class, 'getKardexValorado']);
    Route::get('kardex/reporte/{articulo_id}', [KardexController::class, 'getReportePorArticulo']);
    Route::post('kardex/recalcular', [KardexController::class, 'recalcular']);
    Route::get('kardex/totales', [KardexController::class, 'getTotales']);
    Route::get('kardex/por-articulo/{articulo_id}', [KardexController::class, 'porArticulo']);
    Route::apiResource('kardex', KardexController::class);

    // Reportes
    Route::prefix('reportes')->group(function () {
        // Ventas
        Route::get('ventas', [ReporteController::class, 'ventas']);
        Route::get('ventas/export-excel', [ReporteController::class, 'exportVentasExcel']);
        Route::get('ventas/export-pdf', [ReporteController::class, 'exportVentasPDF']);

        // Compras
        Route::get('compras', [ReporteController::class, 'compras']);
        Route::get('compras/export-excel', [ReporteController::class, 'exportComprasExcel']);

        // Inventario
        Route::get('inventario', [ReporteController::class, 'inventario']);
        Route::get('inventario/export-excel', [ReporteController::class, 'exportInventarioExcel']);

        // Créditos
        Route::get('creditos', [ReporteController::class, 'creditos']);

        // Otros
        Route::get('productos-mas-vendidos', [ReporteController::class, 'productosMasVendidos']);
        Route::get('stock-bajo', [ReporteController::class, 'stockBajo']);
        Route::get('utilidad', [ReporteController::class, 'utilidad']);

        // Utilidades por Sucursal
        Route::get('utilidades-sucursal', [ReporteController::class, 'utilidadesSucursal']);
        Route::get('utilidades-sucursal/export-excel', [ReporteController::class, 'exportUtilidadesSucursalExcel']);
        Route::get('utilidades-sucursal/export-pdf', [ReporteController::class, 'exportUtilidadesSucursalPDF']);

        // Cajas por Sucursal
        Route::get('cajas-sucursal', [ReporteController::class, 'cajasSucursal']);
        Route::get('cajas-sucursal/export-excel', [ReporteController::class, 'exportCajasSucursalExcel']);
        Route::get('cajas-sucursal/export-pdf', [ReporteController::class, 'exportCajasSucursalPDF']);
    });

    // Devoluciones
    Route::apiResource('devoluciones', DevolucionController::class);

    // Conteos Físicos
    Route::post('conteos-fisicos/{id}/generar-ajustes', [ConteoFisicoController::class, 'generarAjustes']);
    Route::apiResource('conteos-fisicos', ConteoFisicoController::class);

    // Inventarios
    Route::get('inventarios/por-item', [InventarioController::class, 'porItem']);
    Route::get('inventarios/por-lotes', [InventarioController::class, 'porLotes']);
    Route::get('inventarios/template/download', [InventarioController::class, 'downloadTemplate']);
    Route::post('inventarios/import', [InventarioController::class, 'importExcel']);
    Route::get('inventarios/export-excel', [InventarioController::class, 'exportExcel']);
    Route::get('inventarios/export-pdf', [InventarioController::class, 'exportPDF']);
    Route::apiResource('inventarios', InventarioController::class);

    // Marcas
    Route::patch('marcas/{marca}/toggle-status', [MarcaController::class, 'toggleStatus']);
    Route::apiResource('marcas', MarcaController::class);

    // Medidas
    Route::patch('medidas/{medida}/toggle-status', [MedidaController::class, 'toggleStatus']);
    Route::apiResource('medidas', MedidaController::class);

    // Monedas
    Route::apiResource('monedas', MonedaController::class);

    // Notificaciones
// Notificaciones
    Route::get('notificaciones/no-leidas', [NotificationController::class, 'unread']);
    Route::put('notificaciones/leer-todas', [NotificationController::class, 'markAllAsRead']);
    Route::put('notificaciones/{id}/leer', [NotificationController::class, 'markAsRead']);
    Route::apiResource('notificaciones', NotificationController::class);

    // Precios
    Route::apiResource('precios', PrecioController::class);

    // Proveedores
    Route::get('proveedores/template/download', [ProveedorController::class, 'downloadTemplate']);
    Route::post('proveedores/import', [ProveedorController::class, 'import']);
    Route::get('proveedores/export-excel', [ProveedorController::class, 'exportExcel']);
    Route::get('proveedores/export-pdf', [ProveedorController::class, 'exportPDF']);
    Route::patch('proveedores/{proveedor}/toggle-status', [ProveedorController::class, 'toggleStatus']);
    Route::apiResource('proveedores', ProveedorController::class);

    // Roles
    Route::apiResource('roles', RolController::class);

    // Sucursales
    Route::patch('sucursales/{sucursal}/toggle-status', [SucursalController::class, 'toggleStatus']);
    Route::apiResource('sucursales', SucursalController::class);

    // Tipos de Pago
    Route::apiResource('tipos-pago', TipoPagoController::class);

    // Tipos de Venta
    Route::apiResource('tipos-venta', TipoVentaController::class);

    // Transacciones de Caja
    Route::get('transacciones-caja/caja/{cajaId}', [TransaccionCajaController::class, 'getByCaja']);
    Route::apiResource('transacciones-caja', TransaccionCajaController::class);

    // Traspasos
    Route::apiResource('traspasos', TraspasoController::class);
    Route::post('traspasos/{traspaso}/aprobar', [TraspasoController::class, 'aprobar']);
    Route::post('traspasos/{traspaso}/recibir', [TraspasoController::class, 'recibir']);
    Route::post('traspasos/{traspaso}/rechazar', [TraspasoController::class, 'rechazar']);

    // Ventas
    Route::get('ventas/productos-inventario', [VentaController::class, 'productosInventario']);
    Route::get('ventas/{id}/imprimir/{formato}', [VentaController::class, 'imprimirComprobante']);
    Route::get('ventas/reporte/detallado-pdf', [VentaController::class, 'exportReporteDetalladoPDF']);
    Route::get('ventas/reporte/general-pdf', [VentaController::class, 'exportReporteGeneralPDF']);
    Route::apiResource('ventas', VentaController::class);
    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('kpis', [DashboardController::class, 'getKpis']);
        Route::get('kpis-filtrados', [DashboardController::class, 'getKpisFiltrados']);
        Route::get('utilidad-articulos', [DashboardController::class, 'getUtilidadArticulos']);
        Route::get('ventas-recientes', [DashboardController::class, 'getVentasRecientes']);
        Route::get('productos-top', [DashboardController::class, 'getProductosTop']);
        Route::get('chart-ventas', [DashboardController::class, 'getVentasChart']);
        Route::get('chart-ventas-filtrado', [DashboardController::class, 'getVentasChartFiltrado']);
        Route::get('chart-inventario', [DashboardController::class, 'getInventarioChart']);
        Route::get('chart-comparativa', [DashboardController::class, 'getComparativaChart']);
        Route::get('proveedores-top', [DashboardController::class, 'getProveedoresTop']);
        Route::get('clientes-frecuentes', [DashboardController::class, 'getClientesFrecuentes']);
        Route::get('productos-bajo-stock', [DashboardController::class, 'getProductosBajoStock']);
        Route::get('productos-mas-comprados', [DashboardController::class, 'getProductosMasComprados']);
        Route::get('top-stock', [DashboardController::class, 'getTopStock']);
        Route::get('alertas', [DashboardController::class, 'getAlertas']);
        Route::get('resumen-cajas', [DashboardController::class, 'getResumenCajas']);
        Route::get('rotacion-inventario', [DashboardController::class, 'getRotacionInventario']);
        Route::get('sucursales', [DashboardController::class, 'getSucursales']);
    });

});

// Ruta de salud/health check (pública)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API funcionando correctamente',
        'timestamp' => now()->toDateTimeString()
    ]);
});