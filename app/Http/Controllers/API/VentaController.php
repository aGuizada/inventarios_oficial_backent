<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\TipoVenta;
use App\Models\TipoPago;
use App\Models\Kardex; // Added Kardex use statement
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Notifications\LowStockNotification;
use App\Notifications\OutOfStockNotification;
use App\Notifications\CreditSaleNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Log;

class VentaController extends Controller
{
    use HasPagination;

    protected $kardexService;

    public function __construct(\App\Services\KardexService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    /**
     * Calcula los totales de una venta basándose en los detalles
     * 
     * @param array $detalles Array de detalles de venta
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
                'subtotal' => $subtotalDetalle
            ];
        }

        // El total es igual al subtotal (no hay descuento global en ventas)
        $total = $subtotal;

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'detalles_calculados' => $detallesCalculados
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja']);

            $searchableFields = [
                'id',
                'num_comprobante',
                'serie_comprobante',
                'tipo_comprobante',
                'cliente.nombre',
                'cliente.num_documento',
                'user.name'
            ];

            // Restringir visibilidad para no administradores (Vendedores)
            $user = $request->user();
            // Asumiendo que el rol 1 es Administrador. Si no es admin, solo ve sus ventas.
            if ($user && $user->rol_id !== 1) {
                $query->where('user_id', $user->id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
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
        } catch (\Exception $e) {
            \Log::error('Error en VentaController@index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener productos disponibles en inventario (con stock > 0)
     * Solo muestra productos con stock disponible mayor a 0
     */
    public function productosInventario(Request $request)
    {
        $almacenId = $request->get('almacen_id');
        $incluirSinStock = $request->get('incluir_sin_stock', false);

        $query = Inventario::with([
            'articulo.categoria',
            'articulo.marca',
            'articulo.medida',
            'articulo.industria',
            'articulo.proveedor',
            'almacen'
        ]);

        // Por defecto, solo mostrar productos con stock > 0
        if (!$incluirSinStock) {
            $query->where('saldo_stock', '>', 0);
        }

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        if ($request->has('categoria_id')) {
            $categoriaId = $request->categoria_id;
            $query->whereHas('articulo', function ($q) use ($categoriaId) {
                $q->where('categoria_id', $categoriaId);
            });
        }

        // Aplicar búsqueda si existe
        $searchableFields = [
            'articulo.nombre',
            'articulo.codigo',
            'articulo.marca.nombre',
            'articulo.categoria.nombre'
        ];
        $query = $this->applySearch($query, $request, $searchableFields);

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, ['id', 'saldo_stock'], 'id', 'desc');

        // Si se solicita paginación
        if ($request->has('per_page') || $request->has('page')) {
            $perPage = min((int) $request->get('per_page', 24), 100);
            $paginated = $query->paginate($perPage);

            $paginated->getCollection()->transform(function ($inventario) {
                return [
                    'inventario_id' => $inventario->id,
                    'articulo_id' => $inventario->articulo_id,
                    'almacen_id' => $inventario->almacen_id,
                    'stock_disponible' => $inventario->saldo_stock ?? 0,
                    'cantidad' => $inventario->cantidad,
                    'articulo' => $inventario->articulo,
                    'almacen' => $inventario->almacen,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $paginated->items(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ]
            ]);
        }

        // Comportamiento anterior (sin paginación)
        $inventarios = $query->get();

        $productos = $inventarios->map(function ($inventario) {
            return [
                'inventario_id' => $inventario->id,
                'articulo_id' => $inventario->articulo_id,
                'almacen_id' => $inventario->almacen_id,
                'stock_disponible' => $inventario->saldo_stock ?? 0,
                'cantidad' => $inventario->cantidad,
                'articulo' => $inventario->articulo,
                'almacen' => $inventario->almacen,
            ];
        });

        return response()->json($productos);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'cliente_id' => 'required|exists:clientes,id',
                'user_id' => 'required|exists:users,id',
                'tipo_venta_id' => 'required|exists:tipo_ventas,id',
                'tipo_pago_id' => 'required|exists:tipo_pagos,id',
                'tipo_comprobante' => 'nullable|string|max:50',
                'serie_comprobante' => 'nullable|string|max:50',
                'num_comprobante' => 'nullable|string|max:50',
                'fecha_hora' => 'required|date',
                'estado' => 'boolean',
                'caja_id' => 'nullable|exists:cajas,id',
                'almacen_id' => 'required|exists:almacenes,id',
                'detalles' => 'required|array|min:1',
                'detalles.*.articulo_id' => 'required|exists:articulos,id',
                'detalles.*.cantidad' => 'required|numeric|min:0.01',
                'detalles.*.precio' => 'required|numeric|min:0',
                'detalles.*.descuento' => 'nullable|numeric|min:0',
                'detalles.*.unidad_medida' => 'nullable|string|in:Unidad,Paquete,Centimetro',
                'pagos' => 'nullable|array',
                'pagos.*.tipo_pago_id' => 'required|exists:tipo_pagos,id',
                'pagos.*.monto' => 'required|numeric|min:0',
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'El cliente seleccionado no existe.',
                'user_id.required' => 'El usuario es obligatorio.',
                'user_id.exists' => 'El usuario seleccionado no existe.',
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
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
                'caja_id.exists' => 'La caja seleccionada no existe.',
                'detalles.required' => 'Los detalles de la venta son obligatorios.',
                'detalles.array' => 'Los detalles deben ser un arreglo.',
                'detalles.*.articulo_id.required' => 'El artículo es obligatorio en los detalles.',
                'detalles.*.articulo_id.exists' => 'El artículo seleccionado no existe.',
                'detalles.*.cantidad.required' => 'La cantidad es obligatoria en los detalles.',
                'detalles.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
                'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
                'detalles.*.precio.required' => 'El precio es obligatorio en los detalles.',
                'detalles.*.precio.numeric' => 'El precio debe ser un número.',
                'detalles.*.descuento.numeric' => 'El descuento debe ser un número.',
                'pagos.array' => 'Los pagos deben ser un arreglo.',
                'pagos.*.tipo_pago_id.required' => 'El tipo de pago es obligatorio en los pagos.',
                'pagos.*.tipo_pago_id.exists' => 'El tipo de pago seleccionado no existe en los pagos.',
                'pagos.*.monto.required' => 'El monto es obligatorio en los pagos.',
                'pagos.*.monto.numeric' => 'El monto debe ser un número.',
                'pagos.*.monto.min' => 'El monto debe ser al menos 0.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Calcular totales en el backend
            $resultadoCalculo = $this->calcularTotales($request->detalles);

            // Validar stock disponible antes de crear la venta
            $almacenId = $request->input('almacen_id'); // Necesitamos el almacén para validar stock

            if (!$almacenId) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => [
                        'almacen_id' => ['El almacén es requerido para validar el stock disponible.']
                    ]
                ], 422);
            }

            foreach ($request->detalles as $index => $detalle) {
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (float) $detalle['cantidad']; // Permitir decimales para Centimetro

                // Buscar inventario del artículo
                $inventario = Inventario::where('articulo_id', $articuloId)
                    ->where('almacen_id', $almacenId)
                    ->first();

                if (!$inventario) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.articulo_id" => ["El artículo no está disponible en el inventario del almacén seleccionado."]
                        ]
                    ], 422);
                }

                // Calcular cantidad a deducir según unidad de medida
                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad';
                $articulo = Articulo::find($articuloId);
                $cantidadDeducir = $cantidadVenta;

                if ($unidadMedida === 'Paquete' && $articulo) {
                    $cantidadDeducir = $cantidadVenta * ($articulo->unidad_envase > 0 ? $articulo->unidad_envase : 1);
                } elseif ($unidadMedida === 'Centimetro') {
                    $cantidadDeducir = $cantidadVenta / 100;
                }

                // Validar que el stock disponible sea suficiente
                $stockDisponible = $inventario->saldo_stock ?? 0;

                // Permitir vender hasta que el stock llegue a 0
                // Solo validar que el stock disponible sea suficiente para la cantidad a deducir
                if ($stockDisponible < $cantidadDeducir) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.cantidad" => [
                                "Stock insuficiente. Disponible: {$stockDisponible} (Unidades). Solicitado: {$cantidadDeducir} (Unidades) para {$cantidadVenta} {$unidadMedida}(s).",
                                "Artículo: " . ($articulo ? $articulo->nombre : "ID {$articuloId}")
                            ]
                        ]
                    ], 422);
                }
            }

            // Preparar datos de la venta con el total calculado
            // Excluir campos que no pertenecen al modelo Venta
            $ventaData = $request->except(['detalles', 'pagos', 'total', 'almacen_id', 'numero_cuotas', 'tiempo_dias_cuota']);
            $ventaData['total'] = $resultadoCalculo['total'];

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
                $unidadMedida = $detalle['unidad_medida'];

                $articulo = Articulo::find($articuloId);
                $cantidadDeducir = $cantidadVenta;

                if ($unidadMedida === 'Paquete' && $articulo) {
                    $cantidadDeducir = $cantidadVenta * ($articulo->unidad_envase > 0 ? $articulo->unidad_envase : 1);
                } elseif ($unidadMedida === 'Centimetro') {
                    $cantidadDeducir = $cantidadVenta / 100;
                }

                // Registrar movimiento en Kardex y actualizar stock usando KardexService
                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $detalle['articulo_id'],
                    'almacen_id' => $almacenId,
                    'fecha' => $request->fecha_hora,
                    'tipo_movimiento' => 'venta',
                    'documento_tipo' => $request->tipo_comprobante ?? 'ticket',
                    'documento_numero' => $request->num_comprobante ?? 'S/N',
                    'cantidad_entrada' => 0,
                    'cantidad_salida' => $cantidadDeducir,
                    'costo_unitario' => $articulo->precio_costo ?? 0, // Usar costo del artículo
                    'precio_unitario' => $detalle['precio'], // Precio de venta (ya calculado)
                    'observaciones' => 'Venta ' . ($request->tipo_comprobante ?? 'ticket') . ' ' . ($request->num_comprobante ?? ''),
                    'usuario_id' => $request->user_id,
                    'venta_id' => $venta->id
                ]);
            }

            // Registrar crédito si es una venta a crédito
            if ($request->has('numero_cuotas') && $request->has('tiempo_dias_cuota')) {
                $tipoVenta = TipoVenta::find($request->tipo_venta_id);
                $nombreTipoVenta = $tipoVenta ? strtolower(trim($tipoVenta->nombre_tipo_ventas)) : '';

                if (strpos($nombreTipoVenta, 'crédito') !== false || strpos($nombreTipoVenta, 'credito') !== false) {
                    // Calcular fecha del próximo pago
                    $fechaInicio = new \DateTime($request->fecha_hora);
                    $fechaInicio->modify('+' . $request->tiempo_dias_cuota . ' days');

                    \App\Models\CreditoVenta::create([
                        'venta_id' => $venta->id,
                        'cliente_id' => $request->cliente_id,
                        'numero_cuotas' => $request->numero_cuotas,
                        'tiempo_dias_cuota' => $request->tiempo_dias_cuota,
                        'total' => $resultadoCalculo['total'],
                        'estado' => 'pendiente',
                        'proximo_pago' => $fechaInicio->format('Y-m-d H:i:s')
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
                        'referencia' => $pago['referencia'] ?? null
                    ]);
                }
            } else {
                // Si no hay pagos mixtos, registrar el pago único por defecto
                \App\Models\DetallePago::create([
                    'venta_id' => $venta->id,
                    'tipo_pago_id' => $request->tipo_pago_id,
                    'monto' => $resultadoCalculo['total'], // Usar el total calculado
                    'referencia' => null
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

            // Reload venta with relationships for notifications
            $venta->load(['detalles.articulo', 'tipoVenta', 'cliente']);

            // Manually trigger stock check notifications (because Observer runs before detalles are created)
            Log::info('Manually triggering stock check for venta_id: ' . $venta->id);
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
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear la venta',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show($id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}"
            ], 404);
        }

        $venta->load(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'detalles.articulo']);
        return response()->json($venta);
    }

    public function update(Request $request, $id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}"
            ], 404);
        }

        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'user_id' => 'required|exists:users,id',
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
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
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

        $venta->update($request->all());

        return response()->json($venta);
    }

    public function destroy($id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
                'error' => "No se encontró una venta con el ID: {$id}"
            ], 404);
        }

        $venta->delete();
        return response()->json(null, 204);
    }

    public function imprimirComprobante($id, $formato)
    {
        $venta = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'detalles.articulo'])->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        if ($formato === 'rollo') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.venta_rollo', compact('venta'));
            $pdf->setPaper([0, 0, 226.77, 500], 'portrait'); // 80mm width (approx 226pt)
        } else {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.venta_carta', compact('venta'));
            $pdf->setPaper('letter', 'portrait');
        }

        return $pdf->download("venta_{$venta->num_comprobante}.pdf");
    }

    /**
     * Exportar reporte detallado de ventas con ganancias
     */
    public function exportReporteDetalladoPDF(Request $request)
    {
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
                'pagos.tipoPago'
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
                ]
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte-ventas-detallado', $datos);
            $pdf->setPaper('a4', 'portrait');

            $fileName = 'reporte_ventas_detallado_' . ($request->fecha_desde ?? 'all') . '_' . ($request->fecha_hasta ?? 'all') . '.pdf';
            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error('Error al exportar reporte detallado PDF: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar reporte general de ventas por fechas
     */
    public function exportReporteGeneralPDF(Request $request)
    {
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
                'caja.sucursal'
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
                    'ventas' => $ventasDelDia
                ];
            }

            $datos = [
                'ventas_por_fecha' => $resumenPorFecha,
                'resumen' => [
                    'total_ventas' => $totalVentas,
                    'cantidad_ventas' => $ventas->count(),
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ]
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte-ventas-general', $datos);
            $pdf->setPaper('a4', 'landscape');

            $fileName = 'reporte_ventas_general_' . ($request->fecha_desde ?? 'all') . '_' . ($request->fecha_hasta ?? 'all') . '.pdf';
            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error('Error al exportar reporte general PDF: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check stock levels after a sale and notify if low or out of stock
     */
    private function checkStockAndNotify(Venta $venta, $almacenId): void
    {
        if (!$venta->detalles || $venta->detalles->isEmpty()) {
            Log::warning('No detalles found for venta_id: ' . $venta->id);
            return;
        }

        Log::info('Checking stock for ' . $venta->detalles->count() . ' products');

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

            if (!$articulo) {
                Log::warning('Articulo not found for detalle_id: ' . $detalle->id);
                continue;
            }

            // Get current inventory for this product
            $inventario = Inventario::where('articulo_id', $articulo->id)
                ->where('almacen_id', $almacenId)
                ->first();

            if (!$inventario) {
                Log::warning('Inventario not found for articulo_id: ' . $articulo->id . ' in almacen_id: ' . $almacenId);
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
