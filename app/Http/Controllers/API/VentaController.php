<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Models\DetalleVenta;
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
            $venta = Venta::create($request->except('detalles'));

            foreach ($request->detalles as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'] ?? 0,
                ]);
            }

            DB::commit();
            $venta->load('detalles');
            return response()->json($venta, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la venta',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
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
