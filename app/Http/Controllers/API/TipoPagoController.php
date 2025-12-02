<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TipoPago;
use Illuminate\Http\Request;

class TipoPagoController extends Controller
{
    public function index()
    {
        $tipoPagos = TipoPago::all();
        return response()->json($tipoPagos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_tipo_pago' => 'required|string|max:100|unique:tipo_pagos',
        ]);

        $tipoPago = TipoPago::create($request->all());

        return response()->json($tipoPago, 201);
    }

    public function show(TipoPago $tipoPago)
    {
        return response()->json($tipoPago);
    }

    public function update(Request $request, TipoPago $tipoPago)
    {
        $request->validate([
            'nombre_tipo_pago' => 'required|string|max:100|unique:tipo_pagos,nombre_tipo_pago,' . $tipoPago->id,
        ]);

        $tipoPago->update($request->all());

        return response()->json($tipoPago);
    }

    public function destroy(TipoPago $tipoPago)
    {
        $tipoPago->delete();
        return response()->json(null, 204);
    }
}
