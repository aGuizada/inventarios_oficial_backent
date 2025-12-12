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
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    use HasPagination;

    protected $kardexService;

    public function __construct(\App\Services\KardexService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    public function index(Request $request)
    {
        try {
            $query = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'detalles.articulo']);

            $searchableFields = [
                'id',
                'num_comprobante',
                'serie_comprobante',
                'tipo_comprobante',
                'cliente.nombre',
                'cliente.num_documento',
                'user.name'
            ];

            // Filtro por estado (para ver anuladas)
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            // Filtro por devoluciones
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
     */
    public function productosInventario(Request $request)
    {
        $almacenId = $request->get('almacen_id');

        $query = Inventario::with([
            'articulo.categoria',
            'articulo.marca',
            'articulo.medida',
            'articulo.industria',
            'articulo.proveedor',
            'almacen'
        ])
            ->where('saldo_stock', '>', 0);

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        $inventarios = $query->get();

        // Formatear respuesta con información del artículo y stock disponible
        $productos = $inventarios->map(function ($inventario) {
            return [
                'inventario_id' => $inventario->id,
                'articulo_id' => $inventario->articulo_id,
                'almacen_id' => $inventario->almacen_id,
                'stock_disponible' => $inventario->saldo_stock,
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
                'total' => 'required|numeric',
                'estado' => 'boolean',
                'caja_id' => 'nullable|exists:cajas,id',
                'detalles' => 'required|array',
                'detalles.*.articulo_id' => 'required|exists:articulos,id',
                'detalles.*.cantidad' => 'required|integer|min:1',
                'detalles.*.precio' => 'required|numeric',
                'detalles.*.descuento' => 'nullable|numeric',
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
                'total.required' => 'El total es obligatorio.',
                'total.numeric' => 'El total debe ser un número.',
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
                $cantidadVenta = (int) $detalle['cantidad'];

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

                if ($inventario->saldo_stock < $cantidadDeducir) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.cantidad" => [
                                "Stock insuficiente. Stock disponible: {$inventario->saldo_stock}, solicitado: {$cantidadDeducir} ({$unidadMedida})",
                                "Artículo: " . ($articulo ? $articulo->nombre : "ID {$articuloId}")
                            ]
                        ]
                    ], 422);
                }
            }

            $venta = Venta::create($request->except(['detalles', 'pagos']));

            foreach ($request->detalles as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'unidad_medida' => $detalle['unidad_medida'] ?? 'Unidad',
                ]);

                // Calcular cantidad a deducir según unidad de medida
                $articuloId = (int) $detalle['articulo_id'];
                $cantidadVenta = (int) $detalle['cantidad'];
                $unidadMedida = $detalle['unidad_medida'] ?? 'Unidad';

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
                    'precio_unitario' => $detalle['precio'], // Precio de venta
                    'observaciones' => 'Venta ' . ($request->tipo_comprobante ?? 'ticket') . ' ' . ($request->num_comprobante ?? ''),
                    'usuario_id' => $request->user_id,
                    'venta_id' => $venta->id
                ]);
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
                    'monto' => $request->total,
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
            $venta->load('detalles');
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
}
