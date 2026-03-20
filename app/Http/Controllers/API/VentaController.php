<?php

namespace App\Http\Controllers\API;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Http\Traits\HasPagination;
use App\Models\Almacen;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Kardex; // Added Kardex use statement
use App\Models\TipoVenta;
use App\Models\Venta;
use App\Notifications\CreditSaleNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OutOfStockNotification;
use App\Support\ApiError;
use App\Support\VentaCantidadConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VentaController extends Controller
{
    use HasPagination;

    protected $kardexService;

    public function __construct(
        \App\Services\KardexService $kardexService,
        private readonly \App\Services\Venta\AnularVentaService $anularVentaService
    ) {
        $this->kardexService = $kardexService;
    }

    /**
     * Agrega la URL completa de la imagen al artículo
     * La fotografia se guarda solo como nombre de archivo, no como ruta completa
     */
    private function addImageUrl($articulo)
    {
        if ($articulo->fotografia) {
            // Obtener la URL base y asegurarse de que no termine en /api
            $baseUrl = rtrim(config('app.url'), '/');
            // Si termina en /api, removerlo para evitar duplicación
            if (substr($baseUrl, -4) === '/api') {
                $baseUrl = rtrim(substr($baseUrl, 0, -4), '/');
            }

            // Si ya tiene ruta completa (compatibilidad con datos antiguos)
            if (strpos($articulo->fotografia, '/') !== false) {
                // Extraer solo el nombre del archivo
                $filename = basename($articulo->fotografia);
            } else {
                // Solo nombre de archivo (nueva lógica)
                $filename = $articulo->fotografia;
            }

            // Codificar el nombre del archivo para la URL (maneja espacios y caracteres especiales)
            $filenameEncoded = rawurlencode($filename);

            // Usar endpoint de API para servir la imagen directamente desde storage
            $articulo->fotografia_url = $baseUrl.'/api/articulos/imagen/'.$filenameEncoded;
        } else {
            $articulo->fotografia_url = null;
        }

        return $articulo;
    }

    /**
     * Calcula los totales de una venta basándose en los detalles
     *
     * @param  array  $detalles  Array de detalles de venta
     * @return array ['subtotal' => float, 'total' => float, 'detalles_calculados' => array]
     */
    private function calcularTotales($detalles)
    {
        $subtotal = 0;
        $detallesCalculados = [];

        foreach ($detalles as $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $precio = (float) ($detalle['precio'] ?? 0);
            $descuento = (float) ($detalle['descuento'] ?? 0);

            // Calcular subtotal del detalle: (cantidad * precio) - descuento
            $subtotalDetalle = ($cantidad * $precio) - $descuento;
            $subtotalDetalle = max(0, $subtotalDetalle); // No permitir valores negativos

            $subtotal += $subtotalDetalle;

            // Guardar el detalle con el subtotal calculado
            $detallesCalculados[] = [
                'articulo_id' => $detalle['articulo_id'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descuento' => $descuento,
                'unidad_medida' => $detalle['unidad_medida'] ?? 'Unidad',
                'subtotal' => $subtotalDetalle,
            ];
        }

        // El total es igual al subtotal (no hay descuento global en ventas)
        $total = $subtotal;

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'detalles_calculados' => $detallesCalculados,
        ];
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $this->authorize('viewAny', Venta::class);
            $query = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'pagos.tipoPago'])
                ->forAuthenticatedList($user);

            $searchableFields = [
                'id',
                'num_comprobante',
                'serie_comprobante',
                'tipo_comprobante',
                'cliente.nombre',
                'cliente.num_documento',
                'user.name',
            ];

            if ($request->has('estado') && $request->estado !== '' && $request->estado !== null) {
                $estadoFiltro = $request->estado;
                // Compatibilidad con clientes que envían 1/0 (histórico)
                if ($estadoFiltro === '1' || $estadoFiltro === 1 || $estadoFiltro === true) {
                    $estadoFiltro = 'Activo';
                } elseif ($estadoFiltro === '0' || $estadoFiltro === 0 || $estadoFiltro === false) {
                    $estadoFiltro = 'Anulado';
                }
                $query->where('estado', $estadoFiltro);
            }

            if ($request->has('sucursal_id')) {
                $sucursalId = $request->sucursal_id;
                $query->whereHas('caja', function ($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                });
            }

            if ($request->has('has_devoluciones') && $request->has_devoluciones == 'true') {
                $query->whereHas('devoluciones');
            }

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'fecha_hora', 'total', 'num_comprobante'], 'id', 'desc');

            return $this->paginateResponse($query, $request, 15, 100);
        } catch (\Throwable $e) {
            \Log::error('Error en VentaController@index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiError::serverError($e, 'Error al cargar las ventas', 'VentaController@index');
        }
    }

    /**
     * Obtener productos en inventario para ventas (con y sin stock).
     * Incluye todos los registros del almacén; no se eliminan filas con stock 0.
     */
    public function productosInventario(Request $request)
    {
        try {
            $this->authorize('viewAny', Venta::class);
            // Obtener la URL base y asegurarse de que no termine en /api
            $baseUrl = rtrim(config('app.url'), '/');
            // Si termina en /api, removerlo para evitar duplicación
            if (substr($baseUrl, -4) === '/api') {
                $baseUrl = rtrim(substr($baseUrl, 0, -4), '/');
            }

            // Asegurar tipo entero para almacen_id (puede venir como string en query)
            $almacenId = $request->has('almacen_id') ? (int) $request->get('almacen_id') : null;
            // Asegurar que incluir_sin_stock se interprete bien (query string "true" -> true)
            $incluirSinStock = filter_var($request->get('incluir_sin_stock', false), FILTER_VALIDATE_BOOLEAN);

            // Vista tipo catálogo: TODOS los productos (con y sin stock), uno por artículo, stock del almacén seleccionado
            if ($almacenId > 0) {
                $articulosQuery = Articulo::with([
                    'categoria:id,nombre',
                    'marca:id,nombre',
                    'medida:id,nombre_medida',
                    'industria:id,nombre',
                    'proveedor:id,nombre',
                ]);
                // Sin whereHas('inventarios'): listar todos los artículos del sistema (también los que tienen 0 en inventario)

                // Filtro por marca: solo artículos de esa marca (tabla articulos)
                $marcaIdParam = $request->input('marca_id');
                if ($marcaIdParam !== null && $marcaIdParam !== '' && (int) $marcaIdParam > 0) {
                    $articulosQuery->where('articulos.marca_id', (int) $marcaIdParam);
                }

                $searchableFields = ['nombre', 'codigo', 'marca.nombre', 'categoria.nombre'];
                $articulosQuery = $this->applySearch($articulosQuery, $request, $searchableFields);

                // Ordenar por stock en este almacén: primero los que tienen stock, después los sin stock
                $articulosQuery->leftJoin(DB::raw('(SELECT articulo_id, CAST(COALESCE(SUM(saldo_stock), 0) AS DECIMAL(11,3)) as total FROM inventarios WHERE almacen_id = '.(int) $almacenId.' GROUP BY articulo_id) as stock_almacen'), 'articulos.id', '=', 'stock_almacen.articulo_id')
                    ->orderByRaw('COALESCE(stock_almacen.total, 0) DESC')
                    ->orderBy('articulos.id', 'desc')
                    ->select('articulos.*');

                $perPage = min((int) $request->get('per_page', 24), $incluirSinStock ? 10000 : 100);
                $perPage = max(1, $perPage);
                // Al filtrar por marca, mostrar más productos en la primera página (mejor UX cuando hay muchos)
                if ($marcaIdParam !== null && $marcaIdParam !== '' && (int) $marcaIdParam > 0 && $perPage < 100) {
                    $perPage = min(100, $incluirSinStock ? 10000 : 100);
                }
                $paginated = $articulosQuery->paginate($perPage);
                $articuloIds = $paginated->pluck('id')->toArray();

                // Una sola consulta para stock y primer inventario_id (más rápido que dos consultas)
                $inventarioAgg = Inventario::where('almacen_id', $almacenId)
                    ->whereIn('articulo_id', $articuloIds)
                    ->selectRaw('articulo_id, CAST(COALESCE(SUM(saldo_stock), 0) AS DECIMAL(11,3)) as total, MIN(id) as first_id')
                    ->groupBy('articulo_id')
                    ->get();
                $stocks = $inventarioAgg->pluck('total', 'articulo_id');
                $primerInventarioId = $inventarioAgg->pluck('first_id', 'articulo_id');

                $almacen = Almacen::find($almacenId);
                $almacenArray = $almacen ? $almacen->toArray() : null;

                $items = $paginated->getCollection()->map(function ($articulo) use ($baseUrl, $almacenId, $stocks, $primerInventarioId, $almacenArray) {
                    $this->addImageUrl($articulo);
                    $fotografiaUrl = $articulo->fotografia_url ?? null;
                    if (! $fotografiaUrl && $articulo->fotografia) {
                        $filename = strpos($articulo->fotografia, '/') !== false ? basename($articulo->fotografia) : $articulo->fotografia;
                        $fotografiaUrl = $baseUrl.'/api/articulos/imagen/'.rawurlencode($filename);
                    }
                    $articuloArray = $articulo->toArray();
                    $articuloArray['fotografia_url'] = $fotografiaUrl;
                    $stock = (float) ($stocks[$articulo->id] ?? 0);

                    return [
                        'inventario_id' => (int) ($primerInventarioId[$articulo->id] ?? 0),
                        'articulo_id' => $articulo->id,
                        'almacen_id' => $almacenId,
                        'stock_disponible' => $stock,
                        'cantidad' => $stock,
                        'articulo' => $articuloArray,
                        'almacen' => $almacenArray,
                    ];
                })->values()->all();

                $response = response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $items,
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ]);
                $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
                $response->header('Pragma', 'no-cache');

                return $response;
            }

            // Sin almacén: listar por inventario (registros articulo por almacén)
            $query = Inventario::with([
                'articulo' => function ($q) {
                    $q->select('id', 'nombre', 'codigo', 'precio_venta', 'precio_uno', 'precio_dos', 'precio_tres', 'precio_cuatro', 'precio_costo_unid', 'precio_costo_paq', 'categoria_id', 'marca_id', 'medida_id', 'industria_id', 'proveedor_id', 'fotografia');
                },
                'articulo.categoria:id,nombre',
                'articulo.marca:id,nombre',
                'articulo.medida:id,nombre_medida',
                'articulo.industria:id,nombre',
                'articulo.proveedor:id,nombre',
                'almacen:id,nombre_almacen,sucursal_id',
            ]);

            if ($incluirSinStock) {
                // Sin filtro por almacén
            } elseif ($request->filled('sucursal_id')) {
                $sucursalId = $request->sucursal_id;
                $query->whereHas('almacen', function ($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                });
            }

            // Filtrar por marca: solo inventarios cuyo artículo pertenece a esa marca
            $marcaIdParam = $request->input('marca_id');
            if ($marcaIdParam !== null && $marcaIdParam !== '' && (int) $marcaIdParam > 0) {
                $marcaId = (int) $marcaIdParam;
                $query->whereHas('articulo', function ($aq) use ($marcaId) {
                    $aq->where('marca_id', $marcaId);
                });
            }

            // Aplicar búsqueda si existe; SIEMPRE incluir productos sin stock (aunque no coincidan con la búsqueda)
            $searchableFields = [
                'articulo.nombre',
                'articulo.codigo',
                'articulo.marca.nombre',
                'articulo.categoria.nombre',
            ];
            $searchNonEmpty = $request->filled('search') && trim((string) $request->search) !== '';
            if ($searchNonEmpty) {
                $query->where(function ($q) use ($request, $searchableFields) {
                    $this->applySearch($q, $request, $searchableFields);
                    $q->orWhere('inventarios.saldo_stock', '<=', 0);
                });
            } else {
                $query = $this->applySearch($query, $request, $searchableFields);
            }

            // Ordenar para que productos con stock 0 aparezcan primero (saldo_stock asc), luego por id
            $query->orderBy('saldo_stock', 'asc')->orderBy('id', 'desc');

            // Si se solicita paginación (igual que cuando era por categoría)
            if ($request->has('per_page') || $request->has('page')) {
                $maxPerPage = $incluirSinStock ? 10000 : 100;
                $perPage = min((int) $request->get('per_page', 24), $maxPerPage);
                $paginated = $query->paginate($perPage);

                $paginated->getCollection()->transform(function ($inventario) use ($baseUrl) {
                    // Usar el mismo método que ArticuloController
                    if ($inventario->articulo) {
                        // Agregar fotografia_url al objeto
                        $this->addImageUrl($inventario->articulo);

                        // Obtener fotografia_url antes de convertir a array
                        $fotografiaUrl = $inventario->articulo->fotografia_url ?? null;

                        // Si no se generó fotografia_url, generarla manualmente
                        if (! $fotografiaUrl && $inventario->articulo->fotografia) {
                            $filename = $inventario->articulo->fotografia;
                            if (strpos($filename, '/') !== false) {
                                $filename = basename($filename);
                            }
                            $fotografiaUrl = $baseUrl.'/api/articulos/imagen/'.rawurlencode($filename);
                        }

                        // Convertir a array
                        $articuloArray = $inventario->articulo->toArray();

                        // Agregar fotografia_url explícitamente al array (siempre)
                        $articuloArray['fotografia_url'] = $fotografiaUrl;
                    } else {
                        $articuloArray = null;
                    }

                    return [
                        'inventario_id' => $inventario->id,
                        'articulo_id' => $inventario->articulo_id,
                        'almacen_id' => $inventario->almacen_id,
                        'stock_disponible' => (float) ($inventario->saldo_stock ?? 0),
                        'cantidad' => (float) ($inventario->cantidad ?? 0),
                        'articulo' => $articuloArray,
                        'almacen' => $inventario->almacen,
                    ];
                });

                $response = response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $paginated->items(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ]);
                // Evitar caché del navegador para que siempre se vean productos con/sin stock actualizados
                $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
                $response->header('Pragma', 'no-cache');

                return $response;
            }

            // Comportamiento anterior (sin paginación)
            $inventarios = $query->get();

            $productos = $inventarios->map(function ($inventario) use ($baseUrl) {
                // Usar el mismo método que ArticuloController
                if ($inventario->articulo) {
                    // Agregar fotografia_url al objeto
                    $this->addImageUrl($inventario->articulo);

                    // Obtener fotografia_url antes de convertir a array
                    $fotografiaUrl = $inventario->articulo->fotografia_url ?? null;

                    // Si no se generó fotografia_url, generarla manualmente
                    if (! $fotografiaUrl && $inventario->articulo->fotografia) {
                        $filename = $inventario->articulo->fotografia;
                        if (strpos($filename, '/') !== false) {
                            $filename = basename($filename);
                        }
                        $fotografiaUrl = $baseUrl.'/api/articulos/imagen/'.rawurlencode($filename);
                    }

                    // Convertir a array
                    $articuloArray = $inventario->articulo->toArray();

                    // Agregar fotografia_url explícitamente al array
                    $articuloArray['fotografia_url'] = $fotografiaUrl;
                } else {
                    $articuloArray = null;
                }

                return [
                    'inventario_id' => $inventario->id,
                    'articulo_id' => $inventario->articulo_id,
                    'almacen_id' => $inventario->almacen_id,
                    'stock_disponible' => (float) ($inventario->saldo_stock ?? 0),
                    'cantidad' => (float) ($inventario->cantidad ?? 0),
                    'articulo' => $articuloArray,
                    'almacen' => $inventario->almacen,
                ];
            });

            $response = response()->json($productos);
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->header('Pragma', 'no-cache');

            return $response;
        } catch (\Exception $e) {
            \Log::error('VentaController@productosInventario', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cargar productos del inventario: '.$e->getMessage(),
                    'data' => [],
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar productos del inventario.',
                'data' => [],
            ], 500);
        }
    }

    public function store(StoreVentaRequest $request)
    {
        $this->authorize('create', Venta::class);
        DB::beginTransaction();
        try {
            // Calcular totales en el backend
            $resultadoCalculo = $this->calcularTotales($request->detalles);

            // Validar stock disponible antes de crear la venta
            $almacenId = $request->input('almacen_id'); // Necesitamos el almacén para validar stock

            if (! $almacenId) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => [
                        'almacen_id' => ['El almacén es requerido para validar el stock disponible.'],
                    ],
                ], 422);
            }

            foreach ($request->detalles as $index => $detalle) {
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (float) $detalle['cantidad']; // Permitir decimales para Centimetro

                // Buscar TODOS los registros de inventario del artículo en el almacén
                $inventarios = Inventario::where('articulo_id', $articuloId)
                    ->where('almacen_id', $almacenId)
                    ->get();

                if ($inventarios->isEmpty()) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.articulo_id" => ['El artículo no está disponible en el inventario del almacén seleccionado.'],
                        ],
                    ], 422);
                }

                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad';
                $articulo = Articulo::find($articuloId);
                $cantidadDeducir = VentaCantidadConverter::toUnidadBase($articulo, $cantidadVenta, $unidadMedida);

                // Validar que el stock disponible sea suficiente
                // CRÍTICO: Usar CAST explícito para obtener el stock con precisión decimal
                // Sumar todos los stocks disponibles de todos los registros de inventario usando SQL directo
                $stockResult = DB::selectOne(
                    'SELECT CAST(SUM(saldo_stock) AS DECIMAL(11,3)) as stock_total 
                     FROM inventarios 
                     WHERE articulo_id = ? AND almacen_id = ?',
                    [$articuloId, $almacenId]
                );

                // CRÍTICO: Redondear stock disponible a 3 decimales para mantener consistencia
                $stockDisponible = round((float) ($stockResult->stock_total ?? 0), 3);

                // Redondear cantidadDeducir a 3 decimales para mantener consistencia
                $cantidadDeducir = round($cantidadDeducir, 3);

                // Permitir vender hasta que el stock llegue a 0
                // Usar comparación con tolerancia para manejar correctamente los decimales
                // Si la diferencia es menor a 0.0001, consideramos que hay stock suficiente
                if (($stockDisponible - $cantidadDeducir) < -0.0001) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.cantidad" => [
                                "Stock insuficiente. Disponible: {$stockDisponible} (Unidades). Solicitado: {$cantidadDeducir} (Unidades) para {$cantidadVenta} {$unidadMedida}(s).",
                                'Artículo: '.($articulo ? $articulo->nombre : "ID {$articuloId}"),
                            ],
                        ],
                    ], 422);
                }
            }

            // Preparar datos de la venta con el total calculado (guardar almacen_id para ediciones futuras)
            $ventaData = $request->except(['detalles', 'pagos', 'total', 'numero_cuotas', 'tiempo_dias_cuota', 'user_id']);
            $ventaData['total'] = $resultadoCalculo['total'];
            $ventaData['almacen_id'] = $almacenId;
            $ventaData['user_id'] = $request->user()->id;

            $venta = Venta::create($ventaData);

            // Usar los detalles calculados
            foreach ($resultadoCalculo['detalles_calculados'] as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'],
                    'unidad_medida' => $detalle['unidad_medida'],
                ]);

                // Calcular cantidad a deducir según unidad de medida
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (float) $detalle['cantidad']; // Permitir decimales para Centimetro
                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad'; // Valor por defecto si no viene

                $articulo = Articulo::find($articuloId);
                $cantidadDeducir = $cantidadVenta;

                // Calcular cantidad a deducir según la unidad de medida seleccionada
                // IMPORTANTE: Todo se maneja en METROS como unidad base
                if ($unidadMedida === 'Paquete' && $articulo) {
                    // Si es paquete, multiplicar por las unidades por paquete
                    $unidadEnvase = (float) ($articulo->unidad_envase ?? 1);
                    $cantidadDeducir = $cantidadVenta * ($unidadEnvase > 0 ? $unidadEnvase : 1);
                } elseif ($unidadMedida === 'Centimetro') {
                    // Si es centímetro, dividir por 100 para convertir a metros (unidad base)
                    // Ejemplo: 0.30 centímetros = 0.003 metros, 30 centímetros = 0.30 metros
                    $cantidadDeducir = $cantidadVenta / 100;
                } elseif ($unidadMedida === 'Metro') {
                    // Si es metro, se descuenta directamente en metros (unidad base)
                    $cantidadDeducir = $cantidadVenta;
                } else {
                    // Si es 'Unidad' u otra, se usa la cantidad directamente (asumiendo que es en metros para productos de metros/centímetros)
                    $cantidadDeducir = $cantidadVenta;
                }

                // Asegurar que cantidadDeducir sea un número válido y redondear a 3 decimales
                $cantidadDeducir = round((float) $cantidadDeducir, 3);

                // CRÍTICO: Formatear el valor a string con 3 decimales para asegurar precisión
                $cantidadSalidaFormateada = number_format($cantidadDeducir, 3, '.', '');
                $cantidadSalidaFinal = (float) $cantidadSalidaFormateada;

                \Log::info("Venta - Descontando stock. Artículo: {$articuloId}, Cantidad: {$cantidadVenta} {$unidadMedida}, A deducir: {$cantidadSalidaFinal}");

                // Registrar movimiento en Kardex y actualizar stock usando KardexService
                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $detalle['articulo_id'],
                    'almacen_id' => $almacenId,
                    'fecha' => $request->fecha_hora,
                    'tipo_movimiento' => 'venta',
                    'documento_tipo' => $request->tipo_comprobante ?? 'ticket',
                    'documento_numero' => $request->num_comprobante ?? 'S/N',
                    'cantidad_entrada' => 0,
                    'cantidad_salida' => $cantidadSalidaFinal,
                    'costo_unitario' => $articulo->precio_costo ?? 0, // Usar costo del artículo
                    'precio_unitario' => $detalle['precio'], // Precio de venta (ya calculado)
                    'observaciones' => 'Venta '.($request->tipo_comprobante ?? 'ticket').' '.($request->num_comprobante ?? ''),
                    'usuario_id' => $request->user()->id,
                    'venta_id' => $venta->id,
                ]);
            }

            // Registrar crédito si es una venta a crédito
            if ($request->has('numero_cuotas') && $request->has('tiempo_dias_cuota')) {
                $tipoVenta = TipoVenta::find($request->tipo_venta_id);
                $nombreTipoVenta = $tipoVenta ? strtolower(trim($tipoVenta->nombre_tipo_ventas)) : '';

                if (strpos($nombreTipoVenta, 'crédito') !== false || strpos($nombreTipoVenta, 'credito') !== false) {
                    // Calcular fecha del próximo pago
                    $fechaInicio = new \DateTime($request->fecha_hora);
                    $fechaInicio->modify('+'.$request->tiempo_dias_cuota.' days');

                    \App\Models\CreditoVenta::create([
                        'venta_id' => $venta->id,
                        'cliente_id' => $request->cliente_id,
                        'numero_cuotas' => $request->numero_cuotas,
                        'tiempo_dias_cuota' => $request->tiempo_dias_cuota,
                        'total' => $resultadoCalculo['total'],
                        'estado' => 'pendiente',
                        'proximo_pago' => $fechaInicio->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Registrar pagos mixtos si existen
            if ($request->has('pagos') && is_array($request->pagos) && count($request->pagos) > 0) {
                foreach ($request->pagos as $pago) {
                    \App\Models\DetallePago::create([
                        'venta_id' => $venta->id,
                        'tipo_pago_id' => $pago['tipo_pago_id'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                    ]);
                }
            } else {
                // Si no hay pagos mixtos, registrar el pago único por defecto
                \App\Models\DetallePago::create([
                    'venta_id' => $venta->id,
                    'tipo_pago_id' => $request->tipo_pago_id,
                    'monto' => $resultadoCalculo['total'], // Usar el total calculado
                    'referencia' => null,
                ]);
            }

            // Actualizar la caja con la información de la venta
            if ($venta->caja_id) {
                $caja = Caja::find($venta->caja_id);
                if ($caja) {
                    $totalVenta = (float) $venta->total;

                    // Obtener tipo de venta
                    $tipoVenta = TipoVenta::find($venta->tipo_venta_id);
                    $nombreTipoVenta = $tipoVenta ? strtolower(trim($tipoVenta->nombre_tipo_ventas)) : '';

                    // Actualizar ventas totales
                    $caja->ventas = ($caja->ventas ?? 0) + $totalVenta;

                    // Actualizar ventas por tipo (contado o crédito)
                    if (strpos($nombreTipoVenta, 'contado') !== false) {
                        $caja->ventas_contado = ($caja->ventas_contado ?? 0) + $totalVenta;
                    } elseif (strpos($nombreTipoVenta, 'crédito') !== false || strpos($nombreTipoVenta, 'credito') !== false) {
                        $caja->ventas_credito = ($caja->ventas_credito ?? 0) + $totalVenta;
                    }

                    // Actualizar pagos por método de pago (usando detalle_pagos)
                    $pagos = \App\Models\DetallePago::where('venta_id', $venta->id)->with('tipoPago')->get();

                    foreach ($pagos as $pago) {
                        $nombreTipoPago = $pago->tipoPago ? strtolower(trim($pago->tipoPago->nombre_tipo_pago)) : '';
                        $montoPago = (float) $pago->monto;

                        if (strpos($nombreTipoPago, 'efectivo') !== false) {
                            $caja->pagos_efectivo = ($caja->pagos_efectivo ?? 0) + $montoPago;
                        } elseif (strpos($nombreTipoPago, 'qr') !== false) {
                            $caja->pagos_qr = ($caja->pagos_qr ?? 0) + $montoPago;
                        } elseif (strpos($nombreTipoPago, 'transferencia') !== false) {
                            $caja->pagos_transferencia = ($caja->pagos_transferencia ?? 0) + $montoPago;
                        }
                    }

                    $caja->save();
                }
            }

            DB::commit();

            // Log para verificar que el commit se hizo correctamente
            \Log::info("VentaController - Commit de transacción realizado para venta_id: {$venta->id}");

            // Verificar el stock después del commit
            foreach ($request->detalles as $detalle) {
                $articuloId = (int) $detalle['articulo_id'];
                $inventario = \App\Models\Inventario::where('articulo_id', $articuloId)
                    ->where('almacen_id', $almacenId)
                    ->first();
                if ($inventario) {
                    \Log::info("Stock después del commit - Articulo ID: {$articuloId}, Stock: {$inventario->saldo_stock}");
                }
            }

            // Reload venta with relationships for notifications
            $venta->load(['detalles.articulo', 'tipoVenta', 'cliente']);

            // Manually trigger stock check notifications (because Observer runs before detalles are created)
            Log::info('Manually triggering stock check for venta_id: '.$venta->id);
            $this->checkStockAndNotify($venta, $almacenId);

            // Invalidar caché del dashboard para reflejar la nueva venta
            Cache::forget('dashboard.kpis');
            Cache::forget('dashboard.ventas_recientes');
            Cache::forget('dashboard.productos_top');
            Cache::forget('dashboard.ventas_chart');

            return response()->json($venta, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al crear venta', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiError::serverError($e, 'Error al crear la venta', 'VentaController@store');
        }
    }

    public function show($id)
    {
        $venta = Venta::find($id);

        if (! $venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}",
            ], 404);
        }

        $this->authorize('view', $venta);
        $venta->load(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'detalles.articulo.medida', 'detalles.articulo.marca']);

        return response()->json($venta);
    }

    public function update(Request $request, $id)
    {
        $venta = Venta::find($id);

        if (! $venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}",
            ], 404);
        }

        $this->authorize('update', $venta);

        // Edición con cambio de productos: se envían detalles + almacen_id (mismo cuerpo que store).
        if ($request->has('detalles') && is_array($request->detalles) && count($request->detalles) > 0) {
            return $this->updateConDetalles($request, $venta);
        }

        // Actualización simple de cabecera (comportamiento anterior, no se toca).
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'tipo_venta_id' => 'required|exists:tipo_ventas,id',
            'tipo_pago_id' => 'required|exists:tipo_pagos,id',
            'tipo_comprobante' => 'nullable|string|max:50',
            'serie_comprobante' => 'nullable|string|max:50',
            'num_comprobante' => 'nullable|string|max:50',
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric',
            'estado' => 'boolean',
            'caja_id' => 'nullable|exists:cajas,id',
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'tipo_venta_id.required' => 'El tipo de venta es obligatorio.',
            'tipo_venta_id.exists' => 'El tipo de venta seleccionado no existe.',
            'tipo_pago_id.required' => 'El tipo de pago es obligatorio.',
            'tipo_pago_id.exists' => 'El tipo de pago seleccionado no existe.',
            'tipo_comprobante.string' => 'El tipo de comprobante debe ser una cadena de texto.',
            'tipo_comprobante.max' => 'El tipo de comprobante no puede tener más de 50 caracteres.',
            'serie_comprobante.string' => 'La serie del comprobante debe ser una cadena de texto.',
            'serie_comprobante.max' => 'La serie del comprobante no puede tener más de 50 caracteres.',
            'num_comprobante.string' => 'El número de comprobante debe ser una cadena de texto.',
            'num_comprobante.max' => 'El número de comprobante no puede tener más de 50 caracteres.',
            'fecha_hora.required' => 'La fecha y hora son obligatorias.',
            'fecha_hora.date' => 'La fecha y hora deben ser una fecha válida.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'estado.boolean' => 'El estado debe ser verdadero o falso.',
            'caja_id.exists' => 'La caja seleccionada no existe.',
        ]);

        $venta->update($request->only([
            'cliente_id', 'tipo_venta_id', 'tipo_pago_id', 'tipo_comprobante', 'serie_comprobante',
            'num_comprobante', 'fecha_hora', 'total', 'estado', 'caja_id',
        ]));

        return response()->json($venta);
    }

    /**
     * Actualiza una venta cambiando productos: revierte inventario y caja de la venta anterior,
     * aplica nuevos detalles, stock y caja. No modifica store() ni el flujo normal de ventas.
     */
    private function updateConDetalles(Request $request, Venta $venta)
    {
        $request->validate([
            'almacen_id' => ($venta->almacen_id ? 'nullable' : 'required').'|exists:almacenes,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.unidad_medida' => 'nullable|string|in:Unidad,Paquete,Centimetro,Metro',
            'pagos' => 'nullable|array',
            'pagos.*.tipo_pago_id' => 'required_with:pagos|exists:tipo_pagos,id',
            'pagos.*.monto' => 'required_with:pagos|numeric|min:0',
        ]);

        // Usar almacén de la venta original (si existe) para revertir y descontar en el mismo almacén
        $almacenId = (int) ($venta->almacen_id ?? $request->almacen_id);
        if ($almacenId <= 0) {
            $almacenId = (int) $request->almacen_id;
        }
        $resultadoCalculo = $this->calcularTotales($request->detalles);
        $nuevoTotal = $resultadoCalculo['total'];
        $detallesCalculados = $resultadoCalculo['detalles_calculados'];

        DB::beginTransaction();
        try {
            $venta->load(['detalles.articulo', 'pagos.tipoPago']);
            $totalAnterior = (float) $venta->total;

            // 1) Revertir caja: restar total y pagos anteriores
            if ($venta->caja_id) {
                $caja = Caja::find($venta->caja_id);
                if ($caja) {
                    $caja->ventas = ($caja->ventas ?? 0) - $totalAnterior;
                    $tipoVentaAnt = TipoVenta::find($venta->tipo_venta_id);
                    $nombreAnt = $tipoVentaAnt ? strtolower(trim($tipoVentaAnt->nombre_tipo_ventas ?? '')) : '';
                    if (strpos($nombreAnt, 'contado') !== false) {
                        $caja->ventas_contado = ($caja->ventas_contado ?? 0) - $totalAnterior;
                    } elseif (strpos($nombreAnt, 'crédito') !== false || strpos($nombreAnt, 'credito') !== false) {
                        $caja->ventas_credito = ($caja->ventas_credito ?? 0) - $totalAnterior;
                    }
                    foreach ($venta->pagos as $pago) {
                        $nombreTipoPago = $pago->tipoPago ? strtolower(trim($pago->tipoPago->nombre_tipo_pago ?? '')) : '';
                        $monto = (float) $pago->monto;
                        if (strpos($nombreTipoPago, 'efectivo') !== false) {
                            $caja->pagos_efectivo = ($caja->pagos_efectivo ?? 0) - $monto;
                        } elseif (strpos($nombreTipoPago, 'qr') !== false) {
                            $caja->pagos_qr = ($caja->pagos_qr ?? 0) - $monto;
                        } elseif (strpos($nombreTipoPago, 'transferencia') !== false) {
                            $caja->pagos_transferencia = ($caja->pagos_transferencia ?? 0) - $monto;
                        }
                    }
                    $caja->save();
                }
            }

            // 2) Devolver stock de los detalles antiguos (entrada en kardex)
            // Primero devolvemos para que la validación de los nuevos detalles vea el stock correcto
            foreach ($venta->detalles as $det) {
                $unidadMedida = $det->unidad_medida ?? 'Unidad';
                $cantidadEntrada = VentaCantidadConverter::toUnidadBaseByArticuloId((int) $det->articulo_id, (float) $det->cantidad, $unidadMedida);
                $articulo = Articulo::find($det->articulo_id);
                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $det->articulo_id,
                    'almacen_id' => $almacenId,
                    'fecha' => $venta->fecha_hora,
                    'tipo_movimiento' => 'Devolucion_edicion_de_venta',
                    'documento_tipo' => $venta->tipo_comprobante ?? 'ticket',
                    'documento_numero' => $venta->num_comprobante ?? 'S/N',
                    'cantidad_entrada' => $cantidadEntrada,
                    'cantidad_salida' => 0,
                    'costo_unitario' => $articulo->precio_costo ?? 0,
                    'precio_unitario' => $det->precio,
                    'observaciones' => 'Devolución por edición venta #'.$venta->id,
                    'usuario_id' => $venta->user_id,
                    'venta_id' => $venta->id,
                ]);
            }

            // 2b) Validar stock de los nuevos detalles (ya con el stock revertido)
            foreach ($request->detalles as $index => $detalle) {
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (float) $detalle['cantidad'];
                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad';
                $cantidadDeducir = VentaCantidadConverter::toUnidadBaseByArticuloId($articuloId, $cantidadVenta, $unidadMedida);
                $stockResult = DB::selectOne(
                    'SELECT CAST(SUM(saldo_stock) AS DECIMAL(11,3)) as stock_total FROM inventarios WHERE articulo_id = ? AND almacen_id = ?',
                    [$articuloId, $almacenId]
                );
                $stockDisponible = round((float) ($stockResult->stock_total ?? 0), 3);
                if (($stockDisponible - $cantidadDeducir) < -0.0001) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => ["detalles.{$index}.cantidad" => ["Stock insuficiente. Disponible: {$stockDisponible}."]],
                    ], 422);
                }
            }

            // 3) Borrar detalles y pagos antiguos
            DetalleVenta::where('venta_id', $venta->id)->delete();
            \App\Models\DetallePago::where('venta_id', $venta->id)->delete();

            // 4) Actualizar cabecera de la venta (persistir almacen_id para ventas antiguas sin dato)
            $venta->update([
                'cliente_id' => $request->cliente_id,
                'tipo_venta_id' => $request->tipo_venta_id,
                'tipo_pago_id' => $request->tipo_pago_id ?? $venta->tipo_pago_id,
                'tipo_comprobante' => $request->tipo_comprobante ?? $venta->tipo_comprobante,
                'serie_comprobante' => $request->serie_comprobante ?? $venta->serie_comprobante,
                'num_comprobante' => $request->num_comprobante ?? $venta->num_comprobante,
                'fecha_hora' => $request->fecha_hora ?? $venta->fecha_hora,
                'total' => $nuevoTotal,
                'almacen_id' => $almacenId,
            ]);

            $credito = \App\Models\CreditoVenta::where('venta_id', $venta->id)->first();
            if ($credito) {
                $credito->update(['total' => $nuevoTotal]);
            }

            // 5) Crear nuevos detalles y descontar stock
            foreach ($detallesCalculados as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'],
                    'unidad_medida' => $detalle['unidad_medida'],
                ]);
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (float) $detalle['cantidad'];
                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad';
                $cantidadSalidaFinal = VentaCantidadConverter::toUnidadBaseByArticuloId($articuloId, $cantidadVenta, $unidadMedida);
                $articulo = Articulo::find($articuloId);
                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $articuloId,
                    'almacen_id' => $almacenId,
                    'fecha' => $request->fecha_hora ?? $venta->fecha_hora,
                    'tipo_movimiento' => 'venta',
                    'documento_tipo' => $request->tipo_comprobante ?? 'ticket',
                    'documento_numero' => $request->num_comprobante ?? $venta->num_comprobante ?? 'S/N',
                    'cantidad_entrada' => 0,
                    'cantidad_salida' => $cantidadSalidaFinal,
                    'costo_unitario' => $articulo->precio_costo ?? 0,
                    'precio_unitario' => $detalle['precio'],
                    'observaciones' => 'Venta (edición) '.($request->tipo_comprobante ?? 'ticket').' '.($request->num_comprobante ?? ''),
                    'usuario_id' => $request->user()->id,
                    'venta_id' => $venta->id,
                ]);
            }

            // 6) Crear nuevos pagos
            if ($request->has('pagos') && is_array($request->pagos) && count($request->pagos) > 0) {
                foreach ($request->pagos as $pago) {
                    \App\Models\DetallePago::create([
                        'venta_id' => $venta->id,
                        'tipo_pago_id' => $pago['tipo_pago_id'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                    ]);
                }
            } else {
                \App\Models\DetallePago::create([
                    'venta_id' => $venta->id,
                    'tipo_pago_id' => $request->tipo_pago_id ?? $venta->tipo_pago_id,
                    'monto' => $nuevoTotal,
                    'referencia' => null,
                ]);
            }

            // 7) Aplicar nuevo total y pagos a la caja
            if ($venta->caja_id) {
                $caja = Caja::find($venta->caja_id);
                if ($caja) {
                    $caja->ventas = ($caja->ventas ?? 0) + $nuevoTotal;
                    $tipoVenta = TipoVenta::find($venta->tipo_venta_id);
                    $nombreTipoVenta = $tipoVenta ? strtolower(trim($tipoVenta->nombre_tipo_ventas ?? '')) : '';
                    if (strpos($nombreTipoVenta, 'contado') !== false) {
                        $caja->ventas_contado = ($caja->ventas_contado ?? 0) + $nuevoTotal;
                    } elseif (strpos($nombreTipoVenta, 'crédito') !== false || strpos($nombreTipoVenta, 'credito') !== false) {
                        $caja->ventas_credito = ($caja->ventas_credito ?? 0) + $nuevoTotal;
                    }
                    $pagos = \App\Models\DetallePago::where('venta_id', $venta->id)->with('tipoPago')->get();
                    foreach ($pagos as $pago) {
                        $nombreTipoPago = $pago->tipoPago ? strtolower(trim($pago->tipoPago->nombre_tipo_pago ?? '')) : '';
                        $montoPago = (float) $pago->monto;
                        if (strpos($nombreTipoPago, 'efectivo') !== false) {
                            $caja->pagos_efectivo = ($caja->pagos_efectivo ?? 0) + $montoPago;
                        } elseif (strpos($nombreTipoPago, 'qr') !== false) {
                            $caja->pagos_qr = ($caja->pagos_qr ?? 0) + $montoPago;
                        } elseif (strpos($nombreTipoPago, 'transferencia') !== false) {
                            $caja->pagos_transferencia = ($caja->pagos_transferencia ?? 0) + $montoPago;
                        }
                    }
                    $caja->save();
                }
            }

            DB::commit();
            Cache::forget('dashboard.kpis');
            Cache::forget('dashboard.ventas_recientes');
            $venta->load(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'detalles.articulo.medida', 'detalles.articulo.marca', 'pagos.tipoPago']);

            return response()->json($venta);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al actualizar venta con detalles', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiError::serverError($e, 'Error al actualizar la venta', 'VentaController@updateConDetalles');
        }
    }

    /**
     * Anula una venta activa: revierte inventario/kardex y totales de caja, marca estado Anulado.
     */
    public function anular(Request $request, $id)
    {
        $user = $request->user();
        $venta = Venta::with(['detalles.articulo', 'pagos.tipoPago', 'credito', 'tipoVenta'])->find($id);

        if (! $venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}",
            ], 404);
        }

        if ($user) {
            $this->authorize('anular', $venta);
        }

        if ($venta->estado !== 'Activo') {
            return response()->json(['message' => 'La venta ya no está activa'], 422);
        }

        $almacenId = $venta->almacen_id ? (int) $venta->almacen_id : null;
        if (! $almacenId) {
            $k = Kardex::where('venta_id', $venta->id)
                ->where('tipo_movimiento', 'venta')
                ->orderBy('id')
                ->first();
            $almacenId = $k ? (int) $k->almacen_id : null;
        }

        if (! $almacenId) {
            return response()->json([
                'message' => 'No se puede determinar el almacén de la venta (falta almacen_id y movimientos de kardex).',
            ], 422);
        }

        try {
            $venta = $this->anularVentaService->anular($venta, $user, $almacenId);

            return response()->json($venta);
        } catch (\Throwable $e) {
            Log::error('VentaController@anular', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiError::serverError($e, 'Error al anular la venta', 'VentaController@anular');
        }
    }

    public function destroy($id)
    {
        $venta = Venta::find($id);

        if (! $venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}",
            ], 404);
        }

        $this->authorize('delete', $venta);
        $venta->delete();

        return response()->json(null, 204);
    }

    public function imprimirComprobante($id, $formato)
    {
        $venta = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'detalles.articulo.marca', 'detalles.articulo.medida', 'pagos.tipoPago'])->find($id);

        if (! $venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        $this->authorize('view', $venta);

        if ($formato === 'rollo') {
            // Calcular el número en letras antes de pasar a la vista
            $total = (float) $venta->total;
            $parteEntera = (int) $total;

            // Asegurar que siempre se calcule el número en letras
            try {
                $numeroEnLetras = ucfirst(strtolower($this->numeroALetras($parteEntera)));
            } catch (\Exception $e) {
                \Log::error('Error al convertir número a letras: '.$e->getMessage());
                $numeroEnLetras = 'CERO';
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.venta_rollo', [
                'venta' => $venta,
                'numeroEnLetras' => $numeroEnLetras,
            ]);
            // 80mm width (226.77pt), altura mínima ajustada - se expandirá según contenido
            $pdf->setPaper([0, 0, 226.77, 300], 'portrait'); // 80mm x ~106mm (ajustable)
            $pdf->setOption('margin-top', 0);
            $pdf->setOption('margin-bottom', 0);
            $pdf->setOption('margin-left', 0);
            $pdf->setOption('margin-right', 0);
            $pdf->setOption('enable-smart-shrinking', false);
            $pdf->setOption('dpi', 72);
        } else {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.venta_carta', compact('venta'));
            $pdf->setPaper('letter', 'portrait');
        }

        return $pdf->download("venta_{$venta->num_comprobante}.pdf");
    }

    /**
     * Helper para convertir número a letras
     */
    public function numeroALetras($numero)
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $decenasEspeciales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $centenas = ['', 'CIEN', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $numero = (int) $numero;
        if ($numero == 0) {
            return 'CERO';
        }
        if ($numero < 10) {
            return $unidades[$numero];
        }
        if ($numero < 20) {
            return $decenasEspeciales[$numero - 10];
        }
        if ($numero < 100) {
            $decena = (int) ($numero / 10);
            $unidad = $numero % 10;
            if ($unidad == 0) {
                return $decenas[$decena];
            }
            if ($decena == 2) {
                return 'VEINTI'.$unidades[$unidad];
            }

            return $decenas[$decena].' Y '.$unidades[$unidad];
        }
        if ($numero < 1000) {
            $centena = (int) ($numero / 100);
            $resto = $numero % 100;
            if ($centena == 1 && $resto == 0) {
                return 'CIEN';
            }
            if ($centena == 1) {
                return 'CIENTO '.$this->numeroALetras($resto);
            }
            if ($resto == 0) {
                return $centenas[$centena];
            }

            return $centenas[$centena].' '.$this->numeroALetras($resto);
        }
        if ($numero < 1000000) {
            $millar = (int) ($numero / 1000);
            $resto = $numero % 1000;
            if ($millar == 1) {
                if ($resto == 0) {
                    return 'MIL';
                }

                return 'MIL '.$this->numeroALetras($resto);
            }
            if ($resto == 0) {
                return $this->numeroALetras($millar).' MIL';
            }

            return $this->numeroALetras($millar).' MIL '.$this->numeroALetras($resto);
        }

        return 'NÚMERO MUY GRANDE';
    }

    /**
     * Exportar reporte detallado de ventas con ganancias
     */
    public function exportReporteDetalladoPDF(Request $request)
    {
        $this->authorize('viewAny', Venta::class);
        $request->validate([
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        try {
            $query = Venta::with([
                'cliente',
                'user',
                'tipoVenta',
                'tipoPago',
                'detalles.articulo',
                'detalles.articulo.categoria',
                'pagos.tipoPago',
            ]);

            // Aplicar filtros
            if ($request->fecha_desde) {
                $query->whereDate('fecha_hora', '>=', $request->fecha_desde);
            }
            if ($request->fecha_hasta) {
                $query->whereDate('fecha_hora', '<=', $request->fecha_hasta);
            }
            if ($request->sucursal_id) {
                $query->whereHas('caja', function ($q) use ($request) {
                    $q->where('sucursal_id', $request->sucursal_id);
                });
            }

            $ventas = $query->orderBy('fecha_hora', 'desc')->get();

            // Calcular ganancias
            $totalVentas = $ventas->sum('total');
            $totalCostos = 0;
            $totalGanancias = 0;

            foreach ($ventas as $venta) {
                foreach ($venta->detalles as $detalle) {
                    $costo = ($detalle->articulo->precio_costo ?? 0) * $detalle->cantidad;
                    $totalCostos += $costo;
                }
            }
            $totalGanancias = $totalVentas - $totalCostos;

            $datos = [
                'ventas' => $ventas,
                'resumen' => [
                    'total_ventas' => $totalVentas,
                    'total_costos' => $totalCostos,
                    'total_ganancias' => $totalGanancias,
                    'margen_ganancia' => $totalVentas > 0 ? ($totalGanancias / $totalVentas) * 100 : 0,
                    'cantidad_ventas' => $ventas->count(),
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ],
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte-ventas-detallado', $datos);
            $pdf->setPaper('a4', 'portrait');

            $fileName = 'reporte_ventas_detallado_'.($request->fecha_desde ?? 'all').'_'.($request->fecha_hasta ?? 'all').'.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error('Error al exportar reporte detallado PDF: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al exportar PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar reporte general de ventas por fechas
     */
    public function exportReporteGeneralPDF(Request $request)
    {
        $this->authorize('viewAny', Venta::class);
        $request->validate([
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        try {
            $query = Venta::with([
                'cliente',
                'user',
                'tipoVenta',
                'tipoPago',
                'caja.sucursal',
                'pagos.tipoPago',
            ]);

            // Aplicar filtros
            if ($request->fecha_desde) {
                $query->whereDate('fecha_hora', '>=', $request->fecha_desde);
            }
            if ($request->fecha_hasta) {
                $query->whereDate('fecha_hora', '<=', $request->fecha_hasta);
            }
            if ($request->sucursal_id) {
                $query->whereHas('caja', function ($q) use ($request) {
                    $q->where('sucursal_id', $request->sucursal_id);
                });
            }

            $ventas = $query->orderBy('fecha_hora', 'desc')->get();

            // Agrupar por fecha
            $ventasPorFecha = $ventas->groupBy(function ($venta) {
                return \Carbon\Carbon::parse($venta->fecha_hora)->format('Y-m-d');
            });

            // Calcular resumen
            $totalVentas = $ventas->sum('total');
            $resumenPorFecha = [];
            foreach ($ventasPorFecha as $fecha => $ventasDelDia) {
                $resumenPorFecha[$fecha] = [
                    'fecha' => $fecha,
                    'cantidad' => $ventasDelDia->count(),
                    'total' => $ventasDelDia->sum('total'),
                    'ventas' => $ventasDelDia,
                ];
            }

            $datos = [
                'ventas_por_fecha' => $resumenPorFecha,
                'resumen' => [
                    'total_ventas' => $totalVentas,
                    'cantidad_ventas' => $ventas->count(),
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ],
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte-ventas-general', $datos);
            $pdf->setPaper('a4', 'landscape');

            $fileName = 'reporte_ventas_general_'.($request->fecha_desde ?? 'all').'_'.($request->fecha_hasta ?? 'all').'.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error('Error al exportar reporte general PDF: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al exportar PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check stock levels after a sale and notify if low or out of stock
     */
    private function checkStockAndNotify(Venta $venta, $almacenId): void
    {
        if (! $venta->detalles || $venta->detalles->isEmpty()) {
            Log::warning('No detalles found for venta_id: '.$venta->id);

            return;
        }

        Log::info('Checking stock for '.$venta->detalles->count().' products');

        // Check if it's a credit sale first
        if ($venta->tipoVenta && strtolower($venta->tipoVenta->nombre_tipo_ventas) === 'crédito') {
            $cliente = $venta->cliente;
            if ($cliente) {
                Log::info('Sending credit sale notification');
                NotificationHelper::notifyAdmins(new CreditSaleNotification($venta, $cliente));
            }
        }

        // Check each product's stock
        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            if (! $articulo) {
                Log::warning('Articulo not found for detalle_id: '.$detalle->id);

                continue;
            }

            // Get current inventory for this product
            $inventario = Inventario::where('articulo_id', $articulo->id)
                ->where('almacen_id', $almacenId)
                ->first();

            if (! $inventario) {
                Log::warning('Inventario not found for articulo_id: '.$articulo->id.' in almacen_id: '.$almacenId);

                continue;
            }

            // Usar saldo_stock que es el campo correcto que se actualiza en las ventas
            $currentStock = $inventario->saldo_stock ?? 0;
            $stockMinimo = $articulo->stock_minimo ?? 4; // Usar 4 como mínimo por defecto si no está definido
            Log::info("Articulo '{$articulo->nombre}' - Stock actual (saldo_stock): {$currentStock}, Stock mínimo: {$stockMinimo}");

            // Notificar si está sin stock (0 o negativo)
            if ($currentStock <= 0) {
                Log::info("Sending OUT OF STOCK notification for '{$articulo->nombre}' - Stock: {$currentStock}");
                NotificationHelper::notifyAdmins(new OutOfStockNotification($articulo));

                continue; // No verificar low stock si ya está sin stock
            }

            // Notificar si el stock está por debajo de 4 (o del stock mínimo definido)
            // Esto incluye cuando el stock está entre 1 y 3 (o hasta el stock mínimo)
            if ($currentStock > 0 && $currentStock < 4) {
                Log::info("Sending LOW STOCK notification for '{$articulo->nombre}' - Stock: {$currentStock}, Mínimo esperado: 4");
                NotificationHelper::notifyAdmins(new LowStockNotification($articulo, $currentStock));
            } elseif ($articulo->stock_minimo && $currentStock > 0 && $currentStock <= $articulo->stock_minimo && $currentStock >= 4) {
                // Si tiene stock mínimo definido y es mayor a 4, usar ese valor
                Log::info("Sending LOW STOCK notification for '{$articulo->nombre}' - Stock: {$currentStock}, Mínimo: {$articulo->stock_minimo}");
                NotificationHelper::notifyAdmins(new LowStockNotification($articulo, $currentStock));
            }
        }
    }
}
