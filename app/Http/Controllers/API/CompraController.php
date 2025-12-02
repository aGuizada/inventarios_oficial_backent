<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompraBase;
use App\Models\DetalleCompra;
use App\Models\CompraContado;
use App\Models\CompraCredito;
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
            'proveedor_id' => 'required|exists:proveedores,id',
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
            'monto_total' => 'required_if:tipo_compra,credito|numeric',
            'monto_pagado' => 'nullable|numeric',
            'saldo_pendiente' => 'required_if:tipo_compra,credito|numeric',
            'numero_cuotas' => 'required_if:tipo_compra,credito|integer',
            'fecha_vencimiento' => 'required_if:tipo_compra,credito|date',
        ]);

        DB::beginTransaction();
        try {
            $compraBase = CompraBase::create($request->except(['detalles', 'monto_total', 'monto_pagado', 'saldo_pendiente', 'numero_cuotas', 'fecha_vencimiento']));

            foreach ($request->detalles as $detalle) {
                DetalleCompra::create([
                    'compra_base_id' => $compraBase->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'subtotal' => $detalle['subtotal'],
                ]);
            }

            if ($request->tipo_compra === 'contado') {
                CompraContado::create([
                    'id' => $compraBase->id,
                    'monto_pagado' => $request->total,
                    'fecha_pago' => $request->fecha_hora,
                ]);
            } else {
                CompraCredito::create([
                    'id' => $compraBase->id,
                    'monto_total' => $request->monto_total,
                    'monto_pagado' => $request->monto_pagado ?? 0,
                    'saldo_pendiente' => $request->saldo_pendiente,
                    'numero_cuotas' => $request->numero_cuotas,
                    'fecha_vencimiento' => $request->fecha_vencimiento,
                    'estado' => 'pendiente',
                ]);
            }

            DB::commit();
            $compraBase->load('detalles');
            return response()->json($compraBase, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear la compra', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(CompraBase $compra)
    {
        $compra->load(['proveedor', 'user', 'almacen', 'caja', 'detalles.articulo', 'compraContado', 'compraCredito']);
        return response()->json($compra);
    }

    public function update(Request $request, CompraBase $compra)
    {
        $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
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
        ]);

        $compra->update($request->all());

        return response()->json($compra);
    }

    public function destroy(CompraBase $compra)
    {
        $compra->delete();
        return response()->json(null, 204);
    }
}
