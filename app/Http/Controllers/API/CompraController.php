<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompraBase;
use App\Models\DetalleCompra;
use App\Models\CompraContado;
use App\Models\CompraCredito;
use App\Models\Proveedor;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index()
    {
        $compras = CompraBase::with(['proveedor', 'user', 'almacen', 'caja', 'detalles'])->get();
        return response()->json($compras);
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
            'total' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
            'almacen_id' => 'required|exists:almacenes,id',
            'caja_id' => 'nullable|exists:cajas,id',
            'descuento_global' => 'nullable|numeric',
            'tipo_compra' => 'required|in:contado,credito',
            'detalles' => 'required|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric',
            'detalles.*.descuento' => 'nullable|numeric',
            'detalles.*.subtotal' => 'required|numeric',
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

            // Preparar datos de la compra
            $compraData = $request->except(['detalles', 'numero_cuotas', 'monto_pagado', 'proveedor_nombre']);
            $compraData['proveedor_id'] = $proveedorId;
            
            // Asegurar tipos correctos
            $compraData['user_id'] = (int)$compraData['user_id'];
            $compraData['almacen_id'] = (int)$compraData['almacen_id'];
            $compraData['total'] = (float)$compraData['total'];
            
            // caja_id es requerido según la migración
            if (!isset($compraData['caja_id']) || empty($compraData['caja_id'])) {
                // Si no se proporciona, usar la primera caja disponible o lanzar error
                $primeraCaja = \App\Models\Caja::first();
                if (!$primeraCaja) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear la compra',
                        'message' => 'No hay cajas disponibles en el sistema. Por favor, configure al menos una caja.'
                    ], 400);
                }
                $compraData['caja_id'] = $primeraCaja->id;
            } else {
                $compraData['caja_id'] = (int)$compraData['caja_id'];
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

            // Crear detalles
            foreach ($request->detalles as $index => $detalle) {
                if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                    \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                    continue;
                }
                
                $articuloId = (int)$detalle['articulo_id'];
                
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
                    'cantidad' => (int)$detalle['cantidad'],
                    'precio' => (float)($detalle['precio_unitario'] ?? $detalle['precio'] ?? 0),
                    'descuento' => (float)($detalle['descuento'] ?? 0),
                ]);
                
                // Actualizar inventario
                $cantidadComprada = (int)$detalle['cantidad'];
                $almacenId = (int)$compraData['almacen_id'];
                
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
            }

            if ($request->tipo_compra === 'contado') {
                CompraContado::create([
                    'id' => $compraBase->id,
                    'fecha_pago' => $compraData['fecha_hora'],
                    'metodo_pago' => 'efectivo', // Valor por defecto, puede ser configurable
                    'referencia_pago' => null,
                ]);
            } else {
                // Para crédito, necesitamos los campos correctos
                CompraCredito::create([
                    'id' => $compraBase->id,
                    'num_cuotas' => $request->numero_cuotas ?? 1,
                    'frecuencia_dias' => 30, // Valor por defecto (mensual)
                    'cuota_inicial' => $request->monto_pagado ?? 0,
                    'tipo_pago_cuota' => null,
                    'dias_gracia' => 0,
                    'interes_moratorio' => 0.00,
                    'estado_credito' => 'Pendiente',
                ]);
            }

            DB::commit();
            $compraBase->load('detalles');
            return response()->json($compraBase, 201);
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
        return response()->json($compra);
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
            'total' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
            'almacen_id' => 'required|exists:almacenes,id',
            'caja_id' => 'nullable|exists:cajas,id',
            'descuento_global' => 'nullable|numeric',
            'tipo_compra' => 'required|in:contado,credito',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric',
            'detalles.*.descuento' => 'nullable|numeric',
            'detalles.*.subtotal' => 'required|numeric',
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

            // Preparar datos de la compra
            $compraData = $request->except(['detalles', 'proveedor_nombre']);
            $compraData['proveedor_id'] = $proveedorId;
            
            // Asegurar tipos correctos
            $compraData['user_id'] = (int)$compraData['user_id'];
            $compraData['almacen_id'] = (int)$compraData['almacen_id'];
            $compraData['total'] = (float)$compraData['total'];
            
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

            // Crear nuevos detalles
            if ($request->has('detalles') && is_array($request->detalles)) {
                foreach ($request->detalles as $index => $detalle) {
                    if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                        \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                        continue;
                    }
                    
                    $articuloId = (int)$detalle['articulo_id'];
                    
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
                        'cantidad' => (int)$detalle['cantidad'],
                        'precio' => (float)($detalle['precio_unitario'] ?? $detalle['precio'] ?? 0),
                        'descuento' => (float)($detalle['descuento'] ?? 0),
                    ]);
                    
                    // Actualizar inventario
                    $cantidadComprada = (int)$detalle['cantidad'];
                    $almacenId = (int)$compraData['almacen_id'];
                    
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
