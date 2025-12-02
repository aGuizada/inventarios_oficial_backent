<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TransaccionCaja;
use Illuminate\Http\Request;

class TransaccionCajaController extends Controller
{
    public function index()
    {
        $transacciones = TransaccionCaja::with(['caja', 'user'])->get();
        return response()->json($transacciones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'user_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'transaccion' => 'required|string|max:50',
            'importe' => 'required|numeric',
            'descripcion' => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:100',
        ]);

        $transaccion = TransaccionCaja::create($request->all());

        return response()->json($transaccion, 201);
    }

    public function show(TransaccionCaja $transaccionCaja)
    {
        $transaccionCaja->load(['caja', 'user']);
        return response()->json($transaccionCaja);
    }

    public function update(Request $request, TransaccionCaja $transaccionCaja)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'user_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'transaccion' => 'required|string|max:50',
            'importe' => 'required|numeric',
            'descripcion' => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:100',
        ]);

        $transaccionCaja->update($request->all());

        return response()->json($transaccionCaja);
    }

    public function destroy(TransaccionCaja $transaccionCaja)
    {
        $transaccionCaja->delete();
        return response()->json(null, 204);
    }
}
