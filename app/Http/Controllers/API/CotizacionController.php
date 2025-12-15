<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Cliente;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    use HasPagination;

    /**
     * Calcula los totales de una cotización basándose en los detalles
     * 
     * @param array $detalles Array de detalles de cotización
     * @return array ['subtotal' => float, 'total' => float, 'detalles_calculados' => array]
     */
    private function calcularTotales($detalles)
    {
        $subtotal = 0;
        $detallesCalculados = [];

        foreach ($detalles as $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $precioUnitario = (float) ($detalle['precio_unitario'] ?? $detalle['precio'] ?? 0);
            $descuento = (float) ($detalle['descuento'] ?? 0);

            // Calcular subtotal del detalle: (cantidad * precio_unitario) - descuento
            $subtotalDetalle = ($cantidad * $precioUnitario) - $descuento;
            $subtotalDetalle = max(0, $subtotalDetalle); // No permitir valores negativos

            $subtotal += $subtotalDetalle;

            // Guardar el detalle con el subtotal calculado
            $detallesCalculados[] = [
                'articulo_id' => $detalle['articulo_id'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'descuento' => $descuento,
                'subtotal' => $subtotalDetalle
            ];
        }

        // El total es igual al subtotal (no hay descuento global en cotizaciones)
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
            $query = Cotizacion::with(['cliente', 'user', 'almacen', 'detalles.articulo']);

            $searchableFields = [
                'id',
                'cliente.nombre',
                'cliente.num_documento',
                'user.name'
            ];

            // Restringir visibilidad para no administradores (Vendedores)
            $user = $request->user();
            // Asumiendo que el rol 1 es Administrador. Si no es admin, solo ve sus cotizaciones.
            if ($user && $user->rol_id !== 1) {
                $query->where('user_id', $user->id);
            }

            if ($request->has('sucursal_id')) {
                $sucursalId = $request->sucursal_id;
                $query->whereHas('almacen', function ($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                });
            }

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'fecha_hora', 'total', 'estado'], 'id', 'desc');

            return $this->paginateResponse($query, $request, 15, 100);
        } catch (\Exception $e) {
            \Log::error('Error en CotizacionController@index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las cotizaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nombre' => 'required_without:cliente_id|string|max:255',
            'user_id' => 'required|exists:users,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_hora' => 'required|date',
            'validez' => 'nullable|string|max:100',
            'plazo_entrega' => 'nullable|string|max:100',
            'tiempo_entrega' => 'nullable|string|max:100',
            'lugar_entrega' => 'nullable|string|max:255',
            'forma_pago' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'estado' => 'nullable|string|max:50',
            'detalles' => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calcular totales en el backend
            $resultadoCalculo = $this->calcularTotales($request->detalles);

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

            $cotizacionData = $request->except(['detalles', 'cliente_nombre', 'total']);
            $cotizacionData['cliente_id'] = $clienteId;

            // Asegurar que los campos numéricos sean números
            $cotizacionData['user_id'] = (int) $cotizacionData['user_id'];
            $cotizacionData['almacen_id'] = (int) $cotizacionData['almacen_id'];
            $cotizacionData['total'] = $resultadoCalculo['total']; // Usar el total calculado

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

            // Usar los detalles calculados
            foreach ($resultadoCalculo['detalles_calculados'] as $detalle) {
                DetalleCotizacion::create([
                    'cotizacion_id' => $cotizacion->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => (int) $detalle['cantidad'],
                    'precio' => (float) $detalle['precio_unitario'],
                    'descuento' => (float) $detalle['descuento'],
                ]);
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
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calcular totales en el backend si hay detalles
            $resultadoCalculo = null;
            if ($request->has('detalles') && is_array($request->detalles) && count($request->detalles) > 0) {
                $resultadoCalculo = $this->calcularTotales($request->detalles);
            }

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

            $cotizacionData = $request->except(['detalles', 'cliente_nombre', 'total']);
            $cotizacionData['cliente_id'] = $clienteId;

            $cotizacionData['user_id'] = (int) $cotizacionData['user_id'];
            $cotizacionData['almacen_id'] = (int) $cotizacionData['almacen_id'];
            // Usar el total calculado si hay detalles, sino mantener el existente
            $cotizacionData['total'] = $resultadoCalculo ? $resultadoCalculo['total'] : (float) ($request->total ?? 0);

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

            // Crear nuevos detalles usando los valores calculados
            if ($resultadoCalculo && !empty($resultadoCalculo['detalles_calculados'])) {
                \Log::info('Procesando detalles', [
                    'count' => count($resultadoCalculo['detalles_calculados']),
                    'detalles' => $resultadoCalculo['detalles_calculados'],
                    'cotizacion_id' => $cotizacionId
                ]);

                foreach ($resultadoCalculo['detalles_calculados'] as $index => $detalle) {
                    \Log::info('Procesando detalle', ['index' => $index, 'detalle' => $detalle, 'cotizacion_id' => $cotizacionId]);

                    if (!isset($detalle['articulo_id']) || !isset($detalle['cantidad'])) {
                        \Log::warning('Detalle sin articulo_id o cantidad', ['detalle' => $detalle, 'index' => $index]);
                        continue; // Saltar detalles inválidos
                    }

                    $articuloId = (int) $detalle['articulo_id'];

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

                    $precio = (float) $detalle['precio_unitario'];
                    if ($precio <= 0) {
                        \Log::warning('Precio inválido en detalle', ['detalle' => $detalle, 'precio' => $precio]);
                        continue;
                    }

                    $detalleData = [
                        'cotizacion_id' => $cotizacionId,
                        'articulo_id' => $articuloId,
                        'cantidad' => (int) $detalle['cantidad'],
                        'precio' => $precio,
                        'descuento' => (float) $detalle['descuento'],
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
