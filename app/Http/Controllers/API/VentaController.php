<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index()
    {
        $ventas = Venta::with(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'detalles'])->get();
        return response()->json($ventas);
    }

    /**
     * Obtener productos disponibles en inventario (con stock > 0)
     */
    public function productosInventario(Request $request)
    {
        $almacenId = $request->get('almacen_id');
        
        $query = Inventario::with(['articulo', 'almacen'])
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
                $articuloId = (int)$detalle['articulo_id'];
                $cantidadVenta = (int)$detalle['cantidad'];
                
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
                
                if ($inventario->saldo_stock < $cantidadVenta) {
                    DB::rollBack();
                    $articulo = Articulo::find($articuloId);
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            "detalles.{$index}.cantidad" => [
                                "Stock insuficiente. Stock disponible: {$inventario->saldo_stock}, solicitado: {$cantidadVenta}",
                                "Artículo: " . ($articulo ? $articulo->nombre : "ID {$articuloId}")
                            ]
                        ]
                    ], 422);
                }
            }
            
            $venta = Venta::create($request->except('detalles'));

            foreach ($request->detalles as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'] ?? 0,
                ]);
                
                // Actualizar inventario (reducir stock)
                $articuloId = (int)$detalle['articulo_id'];
                $cantidadVenta = (int)$detalle['cantidad'];
                
                $inventario = Inventario::where('articulo_id', $articuloId)
                    ->where('almacen_id', $almacenId)
                    ->first();
                
                if ($inventario) {
                    $inventario->saldo_stock -= $cantidadVenta;
                    if ($inventario->saldo_stock < 0) {
                        $inventario->saldo_stock = 0;
                    }
                    $inventario->save();
                    
                    // Actualizar stock del artículo (stock general)
                    $articulo = Articulo::find($articuloId);
                    if ($articulo) {
                        $articulo->stock -= $cantidadVenta;
                        if ($articulo->stock < 0) {
                            $articulo->stock = 0;
                        }
                        $articulo->save();
                    }
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
