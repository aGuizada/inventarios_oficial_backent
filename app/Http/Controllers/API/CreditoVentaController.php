<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CreditoVenta;
use Illuminate\Http\Request;

class CreditoVentaController extends Controller
{
    public function index()
    {
        $creditoVentas = CreditoVenta::with(['venta', 'cliente'])->get();
        return response()->json($creditoVentas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:ventas,id|unique:credito_ventas',
            'cliente_id' => 'required|exists:clientes,id',
            'monto_total' => 'required|numeric',
            'monto_pagado' => 'nullable|numeric',
            'saldo_pendiente' => 'required|numeric',
            'numero_cuotas' => 'required|integer',
            'fecha_vencimiento' => 'required|date',
            'estado' => 'nullable|string|max:50',
        ]);

        $creditoVenta = CreditoVenta::create($request->all());

        return response()->json($creditoVenta, 201);
    }

    public function show(CreditoVenta $creditoVenta)
    {
        $creditoVenta->load(['venta', 'cliente', 'cuotas']);
        return response()->json($creditoVenta);
    }

    public function update(Request $request, CreditoVenta $creditoVenta)
    {
        $request->validate([
            'venta_id' => 'required|exists:ventas,id|unique:credito_ventas,venta_id,' . $creditoVenta->id,
            'cliente_id' => 'required|exists:clientes,id',
            'monto_total' => 'required|numeric',
            'monto_pagado' => 'nullable|numeric',
            'saldo_pendiente' => 'required|numeric',
            'numero_cuotas' => 'required|integer',
            'fecha_vencimiento' => 'required|date',
            'estado' => 'nullable|string|max:50',
        ]);

        $creditoVenta->update($request->all());

        return response()->json($creditoVenta);
    }

    public function destroy(CreditoVenta $creditoVenta)
    {
        $creditoVenta->delete();
        return response()->json(null, 204);
    }
}
