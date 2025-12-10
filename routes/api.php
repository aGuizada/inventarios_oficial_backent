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
use App\Http\Controllers\API\CotizacionController;
use App\Http\Controllers\API\CreditoVentaController;
use App\Http\Controllers\API\CuotaCreditoController;
use App\Http\Controllers\API\EmpresaController;
use App\Http\Controllers\API\IndustriaController;
use App\Http\Controllers\API\InventarioController;
use App\Http\Controllers\API\MarcaController;
use App\Http\Controllers\API\MedidaController;
use App\Http\Controllers\API\MonedaController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PrecioController;
use App\Http\Controllers\API\ProveedorController;
use App\Http\Controllers\API\RolController;
use App\Http\Controllers\API\SucursalController;
use App\Http\Controllers\API\TipoPagoController;
use App\Http\Controllers\API\TipoVentaController;
use App\Http\Controllers\API\TransaccionCajaController;
use App\Http\Controllers\API\TraspasoController;
use App\Http\Controllers\API\VentaController;

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

// Rutas protegidas (requieren autenticación)
// Route::middleware('auth:sanctum')->group(function () {
// Logout
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Usuarios
Route::apiResource('users', UserController::class);

// Almacenes
Route::apiResource('almacenes', AlmacenController::class);

// Arqueos de Caja
Route::apiResource('arqueos-caja', ArqueoCajaController::class);

// Artículos
Route::get('articulos/template/download', [ArticuloController::class, 'downloadTemplate']);
Route::post('articulos/import', [ArticuloController::class, 'import']);
Route::apiResource('articulos', ArticuloController::class);

// Cajas
Route::apiResource('cajas', CajaController::class);

// Categorías
Route::apiResource('categorias', CategoriaController::class);

// Clientes
Route::apiResource('clientes', ClienteController::class);

// Compras
Route::apiResource('compras', CompraController::class);

// Cuotas de Compra
Route::apiResource('compra-cuotas', CompraCuotaController::class);
Route::post('compra-cuotas/{id}/pagar', [CompraCuotaController::class, 'pagarCuota']);
Route::get('compra-cuotas/compra-credito/{compraCreditoId}', [CompraCuotaController::class, 'getByCompraCredito']);

// Configuración de Trabajo
Route::apiResource('configuracion-trabajo', ConfiguracionTrabajoController::class);

// Cotizaciones
Route::apiResource('cotizaciones', CotizacionController::class);

// Créditos de Venta
Route::apiResource('creditos-venta', CreditoVentaController::class);

// Cuotas de Crédito
Route::get('cuotas-credito/credito/{creditoId}', [CuotaCreditoController::class, 'getByCredito']);
Route::post('cuotas-credito/{id}/pagar', [CuotaCreditoController::class, 'pagarCuota']);
Route::apiResource('cuotas-credito', CuotaCreditoController::class);

// Empresas
Route::apiResource('empresas', EmpresaController::class);

// Industrias
Route::apiResource('industrias', IndustriaController::class);

// Inventarios
Route::apiResource('inventarios', InventarioController::class);

// Marcas
Route::apiResource('marcas', MarcaController::class);

// Medidas
Route::apiResource('medidas', MedidaController::class);

// Monedas
Route::apiResource('monedas', MonedaController::class);

// Notificaciones
Route::apiResource('notificaciones', NotificationController::class);

// Precios
Route::apiResource('precios', PrecioController::class);

// Proveedores
Route::apiResource('proveedores', ProveedorController::class);

// Roles
Route::apiResource('roles', RolController::class);

// Sucursales
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
Route::apiResource('ventas', VentaController::class);
// });

// Ruta de salud/health check (pública)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API funcionando correctamente',
        'timestamp' => now()->toDateTimeString()
    ]);
});

