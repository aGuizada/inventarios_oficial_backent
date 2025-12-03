<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Cliente;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    public function index()
    {
        $cotizaciones = Cotizacion::with(['cliente', 'user', 'almacen', 'detalles'])->get();
        return response()->json($cotizaciones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nombre' => 'required_without:cliente_id|string|max:255',
            'user_id' => 'required|exists:users,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric',
            'validez' => 'nullable|string|max:100',
            'plazo_entrega' => 'nullable|string|max:100',
            'tiempo_entrega' => 'nullable|string|max:100',
            'lugar_entrega' => 'nullable|string|max:255',
            'forma_pago' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'estado' => 'nullable|string|max:50',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric',
            'detalles.*.precio' => 'nullable|numeric',
            'detalles.*.descuento' => 'nullable|numeric',
            'detalles.*.subtotal' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Si no hay cliente_id pero hay cliente_nombre, crear el cliente
            $clienteId = $request->cliente_id;
            if (!$clienteId && $request->cliente_nombre) {
                try {
                    $cliente = Cliente::create([
                        'nombre' => trim($request->cliente_nombre),
                        'estado' => true
                    ]);
                    $clienteId = $cliente->id;
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear el cliente',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], 500);
                }
            }

            if (!$clienteId) {
                DB::rollBack();
                return response()->json(['error' => 'No se pudo determinar el cliente'], 400);
            }

            $cotizacionData = $request->except(['detalles', 'cliente_nombre']);
            $cotizacionData['cliente_id'] = $clienteId;
            
            // Asegurar que los campos numéricos sean números
            $cotizacionData['user_id'] = (int)$cotizacionData['user_id'];
            $cotizacionData['almacen_id'] = (int)$cotizacionData['almacen_id'];
            $cotizacionData['total'] = (float)$cotizacionData['total'];
            
            // Convertir estado a integer si viene como string
            if (isset($cotizacionData['estado'])) {
                if (is_string($cotizacionData['estado'])) {
                    $cotizacionData['estado'] = $cotizacionData['estado'] === 'Pendiente' ? 1 : 
                                                 ($cotizacionData['estado'] === 'Aprobada' ? 2 : 3);
                }
            } else {
                $cotizacionData['estado'] = 1; // Pendiente por defecto
            }
            
            // Asegurar que tiempo_entrega tenga un valor si es requerido (no nullable en BD)
            if (empty($cotizacionData['tiempo_entrega'])) {
                $cotizacionData['tiempo_entrega'] = '';
            }
            
            // Limpiar campo validez si viene vacío (es nullable dateTime)
            // IMPORTANTE: Si viene como string vacío, NO debe incluirse en el array
            if (isset($cotizacionData['validez'])) {
                $validezValue = trim($cotizacionData['validez']);
                if (empty($validezValue) || $validezValue === '' || $validezValue === null) {
                    unset($cotizacionData['validez']); // No incluir el campo si está vacío
                } else {
                    // Si viene validez, asegurar que sea un formato datetime válido
                    try {
                        $validezDate = \Carbon\Carbon::parse($validezValue);
                        $cotizacionData['validez'] = $validezDate->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        // Si no se puede parsear, eliminar el campo
                        unset($cotizacionData['validez']);
                    }
                }
            }
            
            // Asegurar que fecha_hora tenga el formato correcto
            if (isset($cotizacionData['fecha_hora'])) {
                try {
                    $fechaHora = \Carbon\Carbon::parse($cotizacionData['fecha_hora']);
                    $cotizacionData['fecha_hora'] = $fechaHora->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Si no se puede parsear, usar la fecha actual
                    $cotizacionData['fecha_hora'] = now()->format('Y-m-d H:i:s');
                }
            }
            
            $cotizacion = Cotizacion::create($cotizacionData);

            if ($request->has('detalles')) {
                foreach ($request->detalles as $detalle) {
                    DetalleCotizacion::create([
                        'cotizacion_id' => $cotizacion->id,
                        'articulo_id' => $detalle['articulo_id'],
                        'cantidad' => (int)$detalle['cantidad'],
                        'precio' => (float)($detalle['precio_unitario'] ?? $detalle['precio'] ?? 0),
                        'descuento' => (float)($detalle['descuento'] ?? 0),
                    ]);
                }
            }

            DB::commit();
            $cotizacion->load('detalles');
            return response()->json($cotizacion, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al crear cotización', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al crear la cotización',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente', 'user', 'almacen', 'detalles.articulo']);
        return response()->json($cotizacion);
    }

    public function update(Request $request, $id)
    {
        // Buscar la cotización manualmente para tener mejor control
        $cotizacion = Cotizacion::find($id);
        
        if (!$cotizacion) {
            return response()->json([
                'error' => 'Cotización no encontrada',
                'message' => "No se encontró una cotización con ID {$id}"
            ], 404);
        }
        
        \Log::info('Actualizando cotización', [
            'cotizacion_id' => $cotizacion->id,
            'request_data' => $request->all()
        ]);
        
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nombre' => 'required_without:cliente_id|string|max:255',
            'user_id' => 'required|exists:users,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric',
            'validez' => 'nullable|string|max:100',
            'plazo_entrega' => 'nullable|string|max:100',
            'tiempo_entrega' => 'nullable|string|max:100',
            'lugar_entrega' => 'nullable|string|max:255',
            'forma_pago' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'estado' => 'nullable|string|max:50',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric',
            'detalles.*.descuento' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Si no hay cliente_id pero hay cliente_nombre, crear el cliente
            $clienteId = $request->cliente_id;
            if (!$clienteId && $request->cliente_nombre) {
                try {
                    $cliente = Cliente::create([
                        'nombre' => trim($request->cliente_nombre),
                        'estado' => true
                    ]);
                    $clienteId = $cliente->id;
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Error al crear el cliente',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], 500);
                }
            }

            if (!$clienteId) {
                DB::rollBack();
                return response()->json(['error' => 'No se pudo determinar el cliente'], 400);
            }

            $cotizacionData = $request->except(['detalles', 'cliente_nombre']);
            $cotizacionData['cliente_id'] = $clienteId;
            
            $cotizacionData['user_id'] = (int)$cotizacionData['user_id'];
            $cotizacionData['almacen_id'] = (int)$cotizacionData['almacen_id'];
            $cotizacionData['total'] = (float)$cotizacionData['total'];
            
            if (isset($cotizacionData['estado'])) {
                if (is_string($cotizacionData['estado'])) {
                    $cotizacionData['estado'] = $cotizacionData['estado'] === 'Pendiente' ? 1 : 
                                                 ($cotizacionData['estado'] === 'Aprobada' ? 2 : 3);
                }
            } else {
                $cotizacionData['estado'] = 1; // Pendiente por defecto
            }
            
            if (empty($cotizacionData['tiempo_entrega'])) {
                $cotizacionData['tiempo_entrega'] = '';
            }
            
            if (isset($cotizacionData['validez']) && (empty($cotizacionData['validez']) || $cotizacionData['validez'] === '')) {
                unset($cotizacionData['validez']);
            } elseif (isset($cotizacionData['validez']) && !empty($cotizacionData['validez'])) {
                try {
                    $validezDate = \Carbon\Carbon::parse($cotizacionData['validez']);
                    $cotizacionData['validez'] = $validezDate->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    unset($cotizacionData['validez']);
                }
            }
            
            if (isset($cotizacionData['fecha_hora'])) {
                try {
                    $fechaHora = \Carbon\Carbon::parse($cotizacionData['fecha_hora']);
                    $cotizacionData['fecha_hora'] = $fechaHora->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $cotizacionData['fecha_hora'] = now()->format('Y-m-d H:i:s');
                }
            }
            
            // El ID ya está garantizado porque lo buscamos manualmente
            $cotizacionId = $cotizacion->id;
            
            \Log::info('Cotización encontrada', ['cotizacion_id' => $cotizacionId]);
            
            $cotizacion->update($cotizacionData);
            
            // Refrescar el modelo para asegurar que tiene los datos actualizados
            $cotizacion->refresh();
            
            // Verificar nuevamente el ID después del update
            if (!$cotizacion->id) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Error al actualizar la cotización',
                    'message' => 'La cotización perdió su ID después de la actualización'
                ], 500);
            }
            
            // Usar el ID guardado
            $cotizacionId = $cotizacion->id;

            // Eliminar detalles existentes
            DetalleCotizacion::where('cotizacion_id', $cotizacionId)->delete();

            // Crear nuevos detalles
            if ($request->has('detalles') && is_array($request->detalles) && count($request->detalles) > 0) {
                \Log::info('Procesando detalles', [
                    'count' => count($request->detalles), 
                    'detalles' => $request->detalles,
                    'cotizacion_id' => $cotizacionId
                ]);
                
                foreach ($request->detalles as $index => $detalle) {
                    \Log::info('Procesando detalle', ['index' => $index, 'detalle' => $detalle, 'cotizacion_id' => $cotizacionId]);
                    
                    if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                        \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                        continue; // Saltar detalles inválidos
                    }
                    
                    $articuloId = (int)$detalle['articulo_id'];
                    
                    // Verificar que el artículo existe
                    $articulo = Articulo::find($articuloId);
                    if (!$articulo) {
                        // Verificar si existe en la base de datos directamente
                        $articuloExists = DB::table('articulos')->where('id', $articuloId)->exists();
                        
                        \Log::error('Artículo no encontrado', [
                            'articulo_id' => $articuloId, 
                            'detalle' => $detalle,
                            'cotizacion_id' => $cotizacionId,
                            'exists_in_db' => $articuloExists,
                            'articulos_count' => DB::table('articulos')->count()
                        ]);
                        
                        DB::rollBack();
                        return response()->json([
                            'error' => 'Error al actualizar la cotización',
                            'message' => "El artículo con ID {$articuloId} no existe en la base de datos. Por favor, seleccione un artículo válido del catálogo.",
                            'detalle_index' => $index,
                            'articulo_id' => $articuloId,
                            'suggestion' => 'Elimine este detalle y agregue un artículo válido desde el catálogo de productos'
                        ], 400);
                    }
                    
                    $precio = (float)($detalle['precio_unitario'] ?? $detalle['precio'] ?? 0);
                    if ($precio <= 0) {
                        \Log::warning('Precio inválido en detalle', ['detalle' => $detalle, 'precio' => $precio]);
                        continue;
                    }
                    
                    $detalleData = [
                        'cotizacion_id' => $cotizacionId,
                        'articulo_id' => $articuloId,
                        'cantidad' => (int)$detalle['cantidad'],
                        'precio' => $precio,
                        'descuento' => (float)($detalle['descuento'] ?? 0),
                    ];
                    
                    \Log::info('Intentando crear detalle', ['detalle_data' => $detalleData]);
                    
                    try {
                        DetalleCotizacion::create($detalleData);
                        \Log::info('Detalle creado exitosamente', ['detalle_data' => $detalleData]);
                    } catch (\Exception $e) {
                        \Log::error('Error al crear detalle', [
                            'message' => $e->getMessage(),
                            'detalle' => $detalle,
                            'detalle_data' => $detalleData,
                            'cotizacion_id' => $cotizacionId,
                            'trace' => $e->getTraceAsString()
                        ]);
                        DB::rollBack();
                        return response()->json([
                            'error' => 'Error al crear el detalle de cotización',
                            'message' => $e->getMessage(),
                            'detalle' => $detalle,
                            'detalle_data' => $detalleData,
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ], 500);
                    }
                }
            }

            DB::commit();
            $cotizacion->load('detalles');
            return response()->json($cotizacion);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al actualizar cotización', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al actualizar la cotización',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function destroy(Cotizacion $cotizacion)
    {
        $cotizacion->delete();
        return response()->json(null, 204);
    }
}
