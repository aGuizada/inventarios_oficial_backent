<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\CompraBase;
use App\Models\DetalleCompra;
use App\Models\CompraContado;
use App\Models\CompraCredito;
use App\Models\CompraCuota;
use App\Models\Proveedor;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        try {
            $query = CompraBase::with(['proveedor', 'user', 'almacen', 'caja', 'detalles.articulo', 'compraContado', 'compraCredito.cuotas']);

            $searchableFields = [
                'id',
                'num_comprobante',
                'serie_comprobante',
                'tipo_comprobante',
                'proveedor.nombre',
                'proveedor.num_documento',
                'user.name'
            ];

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'fecha_hora', 'total', 'num_comprobante'], 'id', 'desc');

            return $this->paginateResponse($query, $request, 15, 100);
        } catch (\Exception $e) {
            \Log::error('Error en CompraController@index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las compras',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula los totales de una compra basándose en los detalles
     * 
     * @param array $detalles Array de detalles de compra
     * @param float $descuentoGlobal Descuento global aplicado
     * @return array ['subtotal' => float, 'total' => float, 'detalles_calculados' => array]
     */
    private function calcularTotales($detalles, $descuentoGlobal = 0)
    {
        $subtotal = 0;
        $detallesCalculados = [];

        foreach ($detalles as $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $precioUnitario = (float) ($detalle['precio_unitario'] ?? 0);
            $descuentoIndividual = (float) ($detalle['descuento'] ?? 0);
            
            // Calcular subtotal del detalle: (cantidad * precio_unitario) - descuento_individual
            $subtotalDetalle = ($cantidad * $precioUnitario) - $descuentoIndividual;
            $subtotalDetalle = max(0, $subtotalDetalle); // No permitir valores negativos
            
            $subtotal += $subtotalDetalle;
            
            // Guardar el detalle con el subtotal calculado
            $detallesCalculados[] = [
                'articulo_id' => $detalle['articulo_id'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'descuento' => $descuentoIndividual,
                'subtotal' => $subtotalDetalle
            ];
        }

        // Aplicar descuento global al total
        $descuentoGlobal = (float) $descuentoGlobal;
        $total = max(0, $subtotal - $descuentoGlobal);

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'detalles_calculados' => $detallesCalculados
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'proveedor_nombre' => 'required_without:proveedor_id|string|max:255',
            'user_id' => 'required|exists:users,id',
            'tipo_comprobante' => 'nullable|string|max:50',
            'serie_comprobante' => 'nullable|string|max:50',
            'num_comprobante' => 'nullable|string|max:50',
            'fecha_hora' => 'required|date',
            'estado' => 'nullable|string|max:50',
            'almacen_id' => 'required|exists:almacenes,id',
            'caja_id' => 'nullable|exists:cajas,id',
            'descuento_global' => 'nullable|numeric|min:0',
            'tipo_compra' => 'required|in:contado,credito',
            'detalles' => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            // For credito
            'numero_cuotas' => 'required_if:tipo_compra,credito|integer|min:1',
            'monto_pagado' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Manejar proveedor: crear si no existe
            $proveedorId = $request->proveedor_id;
            if (!$proveedorId && $request->proveedor_nombre) {
                try {
                    $proveedor = Proveedor::create([
                        'nombre' => trim($request->proveedor_nombre),
                        'estado' => true
                    ]);
                    $proveedorId = $proveedor->id;
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear el proveedor',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], 500);
                }
            }

            if (!$proveedorId) {
                DB::rollBack();
                return response()->json(['error' => 'No se pudo determinar el proveedor'], 400);
            }

            // Calcular totales en el backend
            $descuentoGlobal = (float) ($request->descuento_global ?? 0);
            $resultadoCalculo = $this->calcularTotales($request->detalles, $descuentoGlobal);
            
            // Preparar datos de la compra
            $compraData = $request->except(['detalles', 'numero_cuotas', 'monto_pagado', 'proveedor_nombre', 'total']);
            $compraData['proveedor_id'] = $proveedorId;

            // Asegurar tipos correctos
            $compraData['user_id'] = (int) $compraData['user_id'];
            $compraData['almacen_id'] = (int) $compraData['almacen_id'];
            $compraData['total'] = $resultadoCalculo['total']; // Usar el total calculado
            $compraData['descuento_global'] = $descuentoGlobal;

            // Convertir tipo_compra a mayúsculas para que coincida con el enum de la base de datos
            if (isset($compraData['tipo_compra'])) {
                $compraData['tipo_compra'] = strtoupper($compraData['tipo_compra']);
            }

            // Asegurar que los campos de comprobante tengan valores por defecto si no se proporcionan
            // Estos campos son requeridos en la base de datos (no nullable)
            $compraData['tipo_comprobante'] = !empty($compraData['tipo_comprobante']) ? $compraData['tipo_comprobante'] : 'SIN COMPROBANTE';
            $compraData['serie_comprobante'] = $compraData['serie_comprobante'] ?? null;
            $compraData['num_comprobante'] = !empty($compraData['num_comprobante']) ? $compraData['num_comprobante'] : '00000000';

            // Validar que haya una caja abierta
            if (!isset($compraData['caja_id']) || empty($compraData['caja_id'])) {
                // Buscar la primera caja abierta
                $cajaAbierta = Caja::where('estado', 1)->orWhere('estado', '1')->orWhere('estado', true)->first();
                if (!$cajaAbierta) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear la compra',
                        'message' => 'No hay una caja abierta. Por favor, abra una caja antes de realizar compras.'
                    ], 400);
                }
                $compraData['caja_id'] = $cajaAbierta->id;
            } else {
                $compraData['caja_id'] = (int) $compraData['caja_id'];

                // Validar que la caja esté abierta
                $caja = Caja::find($compraData['caja_id']);
                if (!$caja) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear la compra',
                        'message' => 'La caja especificada no existe.'
                    ], 400);
                }

                // Verificar que la caja esté abierta (estado = 1, '1', true, o 'abierta')
                $isCajaOpen = $caja->estado === 1 ||
                    $caja->estado === '1' ||
                    $caja->estado === true ||
                    $caja->estado === 'abierta';

                if (!$isCajaOpen) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear la compra',
                        'message' => 'La caja seleccionada está cerrada. Solo se pueden realizar compras con una caja abierta.'
                    ], 400);
                }
            }

            // Formatear fecha_hora
            if (isset($compraData['fecha_hora'])) {
                try {
                    $fechaHora = \Carbon\Carbon::parse($compraData['fecha_hora']);
                    $compraData['fecha_hora'] = $fechaHora->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $compraData['fecha_hora'] = now()->format('Y-m-d H:i:s');
                }
            }

            $compraBase = CompraBase::create($compraData);

            // Crear detalles usando los valores calculados
            foreach ($resultadoCalculo['detalles_calculados'] as $index => $detalle) {
                if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                    \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                    continue;
                }

                $articuloId = (int) $detalle['articulo_id'];

                // Verificar que el artículo existe
                $articulo = Articulo::find($articuloId);
                if (!$articulo) {
                    \Log::error('Artículo no encontrado', [
                        'articulo_id' => $articuloId,
                        'detalle' => $detalle,
                        'compra_base_id' => $compraBase->id
                    ]);
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear la compra',
                        'message' => "El artículo con ID {$articuloId} no existe en la base de datos.",
                        'detalle_index' => $index,
                        'articulo_id' => $articuloId
                    ], 400);
                }

                DetalleCompra::create([
                    'compra_base_id' => $compraBase->id,
                    'articulo_id' => $articuloId,
                    'cantidad' => (int) $detalle['cantidad'],
                    'precio' => (float) $detalle['precio_unitario'],
                    'descuento' => (float) $detalle['descuento'],
                ]);

                // Actualizar inventario
                $cantidadComprada = (int) $detalle['cantidad'];
                $almacenId = (int) $compraData['almacen_id'];

                \Log::info('Actualizando inventario', [
                    'compra_base_id' => $compraBase->id,
                    'articulo_id' => $articuloId,
                    'almacen_id' => $almacenId,
                    'cantidad' => $cantidadComprada
                ]);

                // Buscar o crear registro de inventario para este almacén y artículo
                $inventario = Inventario::where('almacen_id', $almacenId)
                    ->where('articulo_id', $articuloId)
                    ->first();

                if ($inventario) {
                    // Si existe, actualizar cantidad y saldo_stock
                    $cantidadAnterior = $inventario->cantidad;
                    $saldoAnterior = $inventario->saldo_stock;
                    $inventario->cantidad += $cantidadComprada;
                    $inventario->saldo_stock += $cantidadComprada;
                    $inventario->save();

                    \Log::info('Inventario actualizado', [
                        'inventario_id' => $inventario->id,
                        'cantidad_anterior' => $cantidadAnterior,
                        'cantidad_nueva' => $inventario->cantidad,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $inventario->saldo_stock
                    ]);
                } else {
                    // Si no existe, crear nuevo registro
                    $nuevoInventario = Inventario::create([
                        'almacen_id' => $almacenId,
                        'articulo_id' => $articuloId,
                        'cantidad' => $cantidadComprada,
                        'saldo_stock' => $cantidadComprada,
                        'fecha_vencimiento' => '2099-01-01', // Valor por defecto
                    ]);

                    \Log::info('Nuevo inventario creado', [
                        'inventario_id' => $nuevoInventario->id,
                        'almacen_id' => $almacenId,
                        'articulo_id' => $articuloId,
                        'cantidad' => $cantidadComprada
                    ]);
                }

                // Actualizar stock del artículo (stock general)
                $stockAnterior = $articulo->stock;
                $articulo->stock += $cantidadComprada;
                $articulo->save();

                \Log::info('Stock del artículo actualizado', [
                    'articulo_id' => $articuloId,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $articulo->stock
                ]);

                // Registrar movimiento en Kardex
                try {
                    // Calcular saldo anterior del kardex
                    $kardexAnterior = Kardex::where('articulo_id', $articuloId)
                        ->where('almacen_id', $almacenId)
                        ->orderBy('fecha', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    $saldoKardex = ($kardexAnterior->cantidad_saldo ?? 0) + $cantidadComprada;
                    $costoUnitario = (float) $detalle['precio_unitario'];

                    Kardex::create([
                        'fecha' => $compraData['fecha_hora'],
                        'tipo_movimiento' => 'compra',
                        'documento_tipo' => 'factura',
                        'documento_numero' => $compraData['num_comprobante'],
                        'articulo_id' => $articuloId,
                        'almacen_id' => $almacenId,
                        'cantidad_entrada' => $cantidadComprada,
                        'cantidad_salida' => 0,
                        'cantidad_saldo' => $saldoKardex,
                        'costo_unitario' => $costoUnitario,
                        'costo_total' => $cantidadComprada * $costoUnitario,
                        'observaciones' => 'Compra ' . $compraData['tipo_comprobante'] . ' ' . $compraData['num_comprobante'],
                        'usuario_id' => $compraData['user_id'],
                        'compra_id' => $compraBase->id
                    ]);

                    \Log::info('Kardex registrado para compra', [
                        'articulo_id' => $articuloId,
                        'cantidad' => $cantidadComprada,
                        'saldo' => $saldoKardex
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error al registrar Kardex en compra', [
                        'articulo_id' => $articuloId,
                        'error' => $e->getMessage()
                    ]);
                    // No detener la transacción por error en kardex
                }
            }

            // Usar el valor ya convertido a mayúsculas de $compraData
            $tipoCompra = strtoupper(trim($compraData['tipo_compra'] ?? $request->tipo_compra ?? 'CONTADO'));

            if ($tipoCompra === 'CONTADO') {
                CompraContado::create([
                    'id' => $compraBase->id,
                    'fecha_pago' => $compraData['fecha_hora'],
                    'metodo_pago' => 'efectivo', // Valor por defecto, puede ser configurable
                    'referencia_pago' => null,
                ]);
            } else {
                // Para crédito, necesitamos los campos correctos
                $numCuotas = (int) ($request->numero_cuotas ?? 1);
                $cuotaInicial = (float) ($request->monto_pagado ?? 0);
                $frecuenciaDias = 30; // Valor por defecto (mensual)

                \Log::info('Datos recibidos para crédito', [
                    'numero_cuotas_request' => $request->numero_cuotas,
                    'numero_cuotas_convertido' => $numCuotas,
                    'monto_pagado_request' => $request->monto_pagado,
                    'monto_pagado_convertido' => $cuotaInicial,
                    'tipo_compra' => $tipoCompra
                ]);

                // Validar que numero_cuotas sea válido
                if ($numCuotas < 1) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'El número de cuotas debe ser mayor a 0'
                    ], 400);
                }

                $compraCredito = CompraCredito::create([
                    'id' => $compraBase->id,
                    'num_cuotas' => $numCuotas,
                    'frecuencia_dias' => $frecuenciaDias,
                    'cuota_inicial' => $cuotaInicial,
                    'tipo_pago_cuota' => null,
                    'dias_gracia' => 0,
                    'interes_moratorio' => 0.00,
                    'estado_credito' => 'Pendiente',
                ]);

                // Calcular el saldo pendiente (total - cuota inicial)
                $totalCompra = (float) $compraBase->total;
                $saldoPendiente = $totalCompra - (float) $cuotaInicial;

                \Log::info('Creando cuotas para compra a crédito', [
                    'compra_id' => $compraBase->id,
                    'compra_credito_id' => $compraCredito->id,
                    'total_compra' => $totalCompra,
                    'cuota_inicial' => $cuotaInicial,
                    'saldo_pendiente' => $saldoPendiente,
                    'num_cuotas' => $numCuotas
                ]);

                // Solo crear cuotas si hay saldo pendiente
                if ($saldoPendiente > 0 && $numCuotas > 0) {
                    // Calcular el monto por cuota (redondeado a 2 decimales)
                    $montoPorCuota = round($saldoPendiente / $numCuotas, 2);

                    // Crear las cuotas
                    $fechaBase = \Carbon\Carbon::parse($compraData['fecha_hora']);
                    $totalCuotasCreadas = 0; // Para rastrear el total y ajustar la última cuota

                    \Log::info('Iniciando creación de cuotas', [
                        'monto_por_cuota' => $montoPorCuota,
                        'fecha_base' => $fechaBase->format('Y-m-d'),
                        'frecuencia_dias' => $frecuenciaDias
                    ]);

                    for ($i = 1; $i <= $numCuotas; $i++) {
                        // Calcular fecha de vencimiento (fecha base + (número de cuota * frecuencia en días))
                        $fechaVencimiento = $fechaBase->copy()->addDays($i * $frecuenciaDias);

                        // Para la última cuota, ajustar el monto para asegurar que la suma sea exacta
                        if ($i === $numCuotas) {
                            // La última cuota = saldo pendiente - suma de las cuotas anteriores
                            $montoCuota = round($saldoPendiente - ($montoPorCuota * ($numCuotas - 1)), 2);
                        } else {
                            $montoCuota = $montoPorCuota;
                        }

                        $totalCuotasCreadas += $montoCuota;

                        $cuotaCreada = CompraCuota::create([
                            'compra_credito_id' => $compraCredito->id,
                            'numero_cuota' => $i,
                            'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                            'monto_cuota' => $montoCuota,
                            'monto_pagado' => 0,
                            'saldo_pendiente' => $montoCuota,
                            'fecha_pago' => null,
                            'estado' => 'Pendiente',
                        ]);

                        \Log::info('Cuota creada', [
                            'cuota_id' => $cuotaCreada->id,
                            'numero_cuota' => $i,
                            'monto_cuota' => $montoCuota,
                            'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d')
                        ]);
                    }

                    \Log::info('Cuotas creadas para compra a crédito', [
                        'compra_credito_id' => $compraCredito->id,
                        'num_cuotas' => $numCuotas,
                        'total_compra' => $totalCompra,
                        'cuota_inicial' => $cuotaInicial,
                        'saldo_pendiente' => $saldoPendiente,
                        'monto_por_cuota' => $montoPorCuota,
                        'total_cuotas_creadas' => $totalCuotasCreadas,
                        'diferencia' => abs($saldoPendiente - $totalCuotasCreadas)
                    ]);
                } else {
                    \Log::info('No se crearon cuotas - saldo pendiente es 0 o número de cuotas es 0', [
                        'compra_credito_id' => $compraCredito->id,
                        'saldo_pendiente' => $saldoPendiente,
                        'num_cuotas' => $numCuotas
                    ]);
                }
            }

            // Actualizar la caja con la información de la compra
            try {
                if ($compraBase->caja_id) {
                    $caja = Caja::find($compraBase->caja_id);
                    if ($caja) {
                        $totalCompra = (float) $compraBase->total;
                        $tipoCompra = strtoupper(trim($compraBase->tipo_compra));

                        \Log::info('Actualizando caja con compra', [
                            'compra_id' => $compraBase->id,
                            'caja_id' => $compraBase->caja_id,
                            'total_compra' => $totalCompra,
                            'tipo_compra' => $tipoCompra,
                            'tipo_compra_original' => $compraBase->tipo_compra,
                            'compras_contado_antes' => $caja->compras_contado,
                            'compras_credito_antes' => $caja->compras_credito
                        ]);

                        // Actualizar compras por tipo (contado o crédito) usando increment para evitar problemas de concurrencia
                        if ($tipoCompra === 'CONTADO') {
                            $valorAnterior = (float) ($caja->compras_contado ?? 0);
                            // Usar DB::raw para actualizar directamente en la base de datos
                            DB::table('cajas')
                                ->where('id', $caja->id)
                                ->increment('compras_contado', $totalCompra);

                            // Recargar para obtener el nuevo valor
                            $caja->refresh();

                            \Log::info('Actualizado compras_contado', [
                                'valor_anterior' => $valorAnterior,
                                'total_compra' => $totalCompra,
                                'nuevo_valor' => $caja->compras_contado
                            ]);
                        } elseif ($tipoCompra === 'CREDITO' || $tipoCompra === 'CRÉDITO') {
                            $valorAnterior = (float) ($caja->compras_credito ?? 0);
                            // Usar DB::raw para actualizar directamente en la base de datos
                            DB::table('cajas')
                                ->where('id', $caja->id)
                                ->increment('compras_credito', $totalCompra);

                            // Recargar para obtener el nuevo valor
                            $caja->refresh();

                            \Log::info('Actualizado compras_credito', [
                                'valor_anterior' => $valorAnterior,
                                'total_compra' => $totalCompra,
                                'nuevo_valor' => $caja->compras_credito
                            ]);
                        } else {
                            \Log::warning('Tipo de compra no reconocido', [
                                'tipo_compra' => $tipoCompra,
                                'tipo_compra_original' => $compraBase->tipo_compra
                            ]);
                        }

                        \Log::info('Caja actualizada después de compra', [
                            'compras_contado_despues' => $caja->compras_contado,
                            'compras_credito_despues' => $caja->compras_credito
                        ]);
                    } else {
                        \Log::warning('Caja no encontrada para actualizar', [
                            'caja_id' => $compraBase->caja_id,
                            'compra_id' => $compraBase->id
                        ]);
                    }
                } else {
                    \Log::warning('Compra sin caja_id', [
                        'compra_id' => $compraBase->id
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Error al actualizar la caja con la compra', [
                    'compra_id' => $compraBase->id,
                    'caja_id' => $compraBase->caja_id ?? null,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                // No lanzar excepción para no romper la transacción de la compra
            }

            DB::commit();
            $compraBase->load(['detalles.articulo', 'proveedor', 'user', 'almacen', 'caja', 'compraContado', 'compraCredito.cuotas']);
            return response()->json([
                'success' => true,
                'message' => 'Compra creada exitosamente',
                'data' => $compraBase
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al crear compra', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al crear la compra',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show(CompraBase $compra)
    {
        $compra->load(['proveedor', 'user', 'almacen', 'caja', 'detalles.articulo', 'compraContado', 'compraCredito']);
        return response()->json([
            'success' => true,
            'data' => $compra
        ]);
    }

    public function update(Request $request, $id)
    {
        // Buscar la compra manualmente
        $compra = CompraBase::find($id);

        if (!$compra) {
            return response()->json([
                'error' => 'Compra no encontrada',
                'message' => "No se encontró una compra con ID {$id}"
            ], 404);
        }

        $request->validate([
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'proveedor_nombre' => 'required_without:proveedor_id|string|max:255',
            'user_id' => 'required|exists:users,id',
            'tipo_comprobante' => 'nullable|string|max:50',
            'serie_comprobante' => 'nullable|string|max:50',
            'num_comprobante' => 'nullable|string|max:50',
            'fecha_hora' => 'required|date',
            'estado' => 'nullable|string|max:50',
            'almacen_id' => 'required|exists:almacenes,id',
            'caja_id' => 'nullable|exists:cajas,id',
            'descuento_global' => 'nullable|numeric|min:0',
            'tipo_compra' => 'required|in:contado,credito',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Manejar proveedor: crear si no existe
            $proveedorId = $request->proveedor_id;
            if (!$proveedorId && $request->proveedor_nombre) {
                try {
                    $proveedor = Proveedor::create([
                        'nombre' => trim($request->proveedor_nombre),
                        'estado' => true
                    ]);
                    $proveedorId = $proveedor->id;
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear el proveedor',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], 500);
                }
            }

            if (!$proveedorId) {
                DB::rollBack();
                return response()->json(['error' => 'No se pudo determinar el proveedor'], 400);
            }

            // Guardar valores anteriores para revertir cambios en la caja
            $totalAnterior = (float) $compra->total;
            $tipoCompraAnterior = strtoupper(trim($compra->tipo_compra));
            $cajaIdAnterior = $compra->caja_id;

            // Calcular totales en el backend
            $descuentoGlobal = (float) ($request->descuento_global ?? 0);
            $detallesParaCalcular = $request->has('detalles') && is_array($request->detalles) 
                ? $request->detalles 
                : [];
            
            $resultadoCalculo = $this->calcularTotales($detallesParaCalcular, $descuentoGlobal);

            // Preparar datos de la compra
            $compraData = $request->except(['detalles', 'proveedor_nombre', 'total']);
            $compraData['proveedor_id'] = $proveedorId;

            // Asegurar tipos correctos
            $compraData['user_id'] = (int) $compraData['user_id'];
            $compraData['almacen_id'] = (int) $compraData['almacen_id'];
            $compraData['total'] = $resultadoCalculo['total']; // Usar el total calculado
            $compraData['descuento_global'] = $descuentoGlobal;

            // Formatear fecha_hora
            if (isset($compraData['fecha_hora'])) {
                try {
                    $fechaHora = \Carbon\Carbon::parse($compraData['fecha_hora']);
                    $compraData['fecha_hora'] = $fechaHora->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $compraData['fecha_hora'] = now()->format('Y-m-d H:i:s');
                }
            }

            $compra->update($compraData);
            $compra->refresh();
            $compraId = $compra->id;

            // Obtener detalles existentes antes de eliminarlos para revertir inventario
            $detallesExistentes = DetalleCompra::where('compra_base_id', $compraId)->get();

            // Revertir cambios en inventario de los detalles existentes
            foreach ($detallesExistentes as $detalleExistente) {
                $articuloExistente = Articulo::find($detalleExistente->articulo_id);
                if ($articuloExistente) {
                    $cantidadARevertir = $detalleExistente->cantidad;
                    $almacenIdExistente = $compra->almacen_id;

                    // Revertir en inventario
                    $inventarioExistente = Inventario::where('almacen_id', $almacenIdExistente)
                        ->where('articulo_id', $detalleExistente->articulo_id)
                        ->first();

                    if ($inventarioExistente) {
                        $inventarioExistente->cantidad -= $cantidadARevertir;
                        $inventarioExistente->saldo_stock -= $cantidadARevertir;
                        if ($inventarioExistente->cantidad < 0) {
                            $inventarioExistente->cantidad = 0;
                        }
                        if ($inventarioExistente->saldo_stock < 0) {
                            $inventarioExistente->saldo_stock = 0;
                        }
                        $inventarioExistente->save();
                    }

                    // Revertir stock del artículo
                    $articuloExistente->stock -= $cantidadARevertir;
                    if ($articuloExistente->stock < 0) {
                        $articuloExistente->stock = 0;
                    }
                    $articuloExistente->save();
                }
            }

            // Eliminar detalles existentes
            DetalleCompra::where('compra_base_id', $compraId)->delete();

            // Crear nuevos detalles usando los valores calculados
            if (!empty($resultadoCalculo['detalles_calculados'])) {
                foreach ($resultadoCalculo['detalles_calculados'] as $index => $detalle) {
                    if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                        \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                        continue;
                    }

                    $articuloId = (int) $detalle['articulo_id'];

                    // Verificar que el artículo existe
                    $articulo = Articulo::find($articuloId);
                    if (!$articulo) {
                        \Log::error('Artículo no encontrado', [
                            'articulo_id' => $articuloId,
                            'detalle' => $detalle,
                            'compra_base_id' => $compraId
                        ]);
                        DB::rollBack();
                        return response()->json([
                            'error' => 'Error al actualizar la compra',
                            'message' => "El artículo con ID {$articuloId} no existe en la base de datos.",
                            'detalle_index' => $index,
                            'articulo_id' => $articuloId
                        ], 400);
                    }

                    DetalleCompra::create([
                        'compra_base_id' => $compraId,
                        'articulo_id' => $articuloId,
                        'cantidad' => (int) $detalle['cantidad'],
                        'precio' => (float) $detalle['precio_unitario'],
                        'descuento' => (float) $detalle['descuento'],
                    ]);

                    // Actualizar inventario
                    $cantidadComprada = (int) $detalle['cantidad'];
                    $almacenId = (int) $compraData['almacen_id'];

                    // Buscar o crear registro de inventario para este almacén y artículo
                    $inventario = Inventario::where('almacen_id', $almacenId)
                        ->where('articulo_id', $articuloId)
                        ->first();

                    if ($inventario) {
                        // Si existe, actualizar cantidad y saldo_stock
                        $inventario->cantidad += $cantidadComprada;
                        $inventario->saldo_stock += $cantidadComprada;
                        $inventario->save();
                    } else {
                        // Si no existe, crear nuevo registro
                        Inventario::create([
                            'almacen_id' => $almacenId,
                            'articulo_id' => $articuloId,
                            'cantidad' => $cantidadComprada,
                            'saldo_stock' => $cantidadComprada,
                            'fecha_vencimiento' => '2099-01-01', // Valor por defecto
                        ]);
                    }

                    // Actualizar stock del artículo (stock general)
                    $articulo->stock += $cantidadComprada;
                    $articulo->save();
                }
            }

            // Revertir cambios anteriores en la caja
            if ($cajaIdAnterior) {
                $cajaAnterior = Caja::find($cajaIdAnterior);
                if ($cajaAnterior) {
                    if ($tipoCompraAnterior === 'CONTADO') {
                        $cajaAnterior->compras_contado = max(0, ($cajaAnterior->compras_contado ?? 0) - $totalAnterior);
                    } elseif ($tipoCompraAnterior === 'CREDITO' || $tipoCompraAnterior === 'CRÉDITO') {
                        $cajaAnterior->compras_credito = max(0, ($cajaAnterior->compras_credito ?? 0) - $totalAnterior);
                    }
                    $cajaAnterior->save();
                }
            }

            // Actualizar la caja con los nuevos valores
            if ($compra->caja_id) {
                $caja = Caja::find($compra->caja_id);
                if ($caja) {
                    $totalCompra = (float) $compra->total;
                    $tipoCompra = strtoupper(trim($compra->tipo_compra));

                    // Actualizar compras por tipo (contado o crédito)
                    if ($tipoCompra === 'CONTADO') {
                        $caja->compras_contado = ($caja->compras_contado ?? 0) + $totalCompra;
                    } elseif ($tipoCompra === 'CREDITO' || $tipoCompra === 'CRÉDITO') {
                        $caja->compras_credito = ($caja->compras_credito ?? 0) + $totalCompra;
                    }

                    $caja->save();
                }
            }

            DB::commit();
            $compra->load('detalles');
            return response()->json($compra);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al actualizar compra', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al actualizar la compra',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function destroy(CompraBase $compra)
    {
        DB::beginTransaction();
        try {
            // Obtener detalles de la compra antes de eliminarla
            $detalles = DetalleCompra::where('compra_base_id', $compra->id)->get();

            // Guardar información de la caja antes de eliminar
            $totalCompra = (float) $compra->total;
            $tipoCompra = strtoupper(trim($compra->tipo_compra));
            $cajaId = $compra->caja_id;

            // Revertir cambios en inventario
            foreach ($detalles as $detalle) {
                $articulo = Articulo::find($detalle->articulo_id);
                if ($articulo) {
                    $cantidadARevertir = $detalle->cantidad;
                    $almacenId = $compra->almacen_id;

                    // Revertir en inventario
                    $inventario = Inventario::where('almacen_id', $almacenId)
                        ->where('articulo_id', $detalle->articulo_id)
                        ->first();

                    if ($inventario) {
                        $inventario->cantidad -= $cantidadARevertir;
                        $inventario->saldo_stock -= $cantidadARevertir;
                        if ($inventario->cantidad < 0) {
                            $inventario->cantidad = 0;
                        }
                        if ($inventario->saldo_stock < 0) {
                            $inventario->saldo_stock = 0;
                        }
                        $inventario->save();
                    }

                    // Revertir stock del artículo
                    $articulo->stock -= $cantidadARevertir;
                    if ($articulo->stock < 0) {
                        $articulo->stock = 0;
                    }
                    $articulo->save();
                }
            }

            // Revertir cambios en la caja
            if ($cajaId) {
                $caja = Caja::find($cajaId);
                if ($caja) {
                    if ($tipoCompra === 'CONTADO') {
                        $caja->compras_contado = max(0, ($caja->compras_contado ?? 0) - $totalCompra);
                    } elseif ($tipoCompra === 'CREDITO' || $tipoCompra === 'CRÉDITO') {
                        $caja->compras_credito = max(0, ($caja->compras_credito ?? 0) - $totalCompra);
                    }
                    $caja->save();
                }
            }

            $compra->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al eliminar compra', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'error' => 'Error al eliminar la compra',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
