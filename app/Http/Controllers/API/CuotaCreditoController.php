<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CuotaCredito;
use Illuminate\Http\Request;

class CuotaCreditoController extends Controller
{
    public function index()
    {
        $cuotas = CuotaCredito::with(['credito', 'cobrador'])->get();
        return response()->json($cuotas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'credito_id' => 'required|exists:credito_ventas,id',
            'cobrador_id' => 'nullable|exists:users,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'precio_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuota = CuotaCredito::create($request->all());

        return response()->json($cuota, 201);
    }

    public function show(CuotaCredito $cuotaCredito)
    {
        $cuotaCredito->load(['credito', 'cobrador']);
        return response()->json($cuotaCredito);
    }

    public function update(Request $request, CuotaCredito $cuotaCredito)
    {
        $request->validate([
            'credito_id' => 'required|exists:credito_ventas,id',
            'cobrador_id' => 'nullable|exists:users,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'precio_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuotaCredito->update($request->all());

        return response()->json($cuotaCredito);
    }

    public function destroy(CuotaCredito $cuotaCredito)
    {
        $cuotaCredito->delete();
        return response()->json(null, 204);
    }
}
