<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EmpresaController;
use App\Http\Controllers\API\SucursalController;
use App\Http\Controllers\API\RolController;
use App\Http\Controllers\API\MonedaController;
use App\Http\Controllers\API\CategoriaController;
use App\Http\Controllers\API\MarcaController;
use App\Http\Controllers\API\IndustriaController;
use App\Http\Controllers\API\MedidaController;
use App\Http\Controllers\API\TipoVentaController;
use App\Http\Controllers\API\TipoPagoController;
use App\Http\Controllers\API\PrecioController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ClienteController;
use App\Http\Controllers\API\ProveedorController;
use App\Http\Controllers\API\AlmacenController;
use App\Http\Controllers\API\ArticuloController;
use App\Http\Controllers\API\InventarioController;
use App\Http\Controllers\API\CajaController;
use App\Http\Controllers\API\VentaController;
use App\Http\Controllers\API\CreditoVentaController;
use App\Http\Controllers\API\CuotaCreditoController;
use App\Http\Controllers\API\CotizacionController;
use App\Http\Controllers\API\ArqueoCajaController;
use App\Http\Controllers\API\TransaccionCajaController;
use App\Http\Controllers\API\CompraController;
use App\Http\Controllers\API\CompraCuotaController;
use App\Http\Controllers\API\TraspasoController;
use App\Http\Controllers\API\ConfiguracionTrabajoController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\AuthController;

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

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Base Tables
    Route::apiResource('empresas', EmpresaController::class);
    Route::apiResource('sucursales', SucursalController::class);
    Route::apiResource('roles', RolController::class);
    Route::apiResource('monedas', MonedaController::class);
    Route::apiResource('categorias', CategoriaController::class);
    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('industrias', IndustriaController::class);
    Route::apiResource('medidas', MedidaController::class);
    Route::apiResource('tipo-ventas', TipoVentaController::class);
    Route::apiResource('tipo-pagos', TipoPagoController::class);
    Route::apiResource('precios', PrecioController::class);

    // People & Auth
    Route::apiResource('users', UserController::class);
    Route::apiResource('clientes', ClienteController::class);
    Route::apiResource('proveedores', ProveedorController::class);

    // Inventory
    Route::apiResource('almacenes', AlmacenController::class);
    Route::apiResource('articulos', ArticuloController::class);
    Route::apiResource('inventarios', InventarioController::class);

    // Sales & Cash
    Route::apiResource('cajas', CajaController::class);
    Route::apiResource('ventas', VentaController::class);
    Route::apiResource('credito-ventas', CreditoVentaController::class);
    Route::apiResource('cuotas-credito', CuotaCreditoController::class);
    Route::apiResource('cotizaciones', CotizacionController::class);
    Route::apiResource('arqueo-cajas', ArqueoCajaController::class);
    Route::apiResource('transacciones-cajas', TransaccionCajaController::class);

    // Purchases
    Route::apiResource('compras', CompraController::class);
    Route::apiResource('compra-cuotas', CompraCuotaController::class);

    // Transfers & Config
    Route::apiResource('traspasos', TraspasoController::class);
    Route::apiResource('configuracion-trabajos', ConfiguracionTrabajoController::class);
    Route::apiResource('notifications', NotificationController::class);
});
