<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompraCuota;
use Illuminate\Http\Request;

class CompraCuotaController extends Controller
{
    public function index()
    {
        $cuotas = CompraCuota::with('compraCredito')->get();
        return response()->json($cuotas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'compra_credito_id' => 'required|exists:compras_credito,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'monto_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuota = CompraCuota::create($request->all());

        return response()->json($cuota, 201);
    }

    public function show(CompraCuota $compraCuota)
    {
        $compraCuota->load('compraCredito');
        return response()->json($compraCuota);
    }

    public function update(Request $request, CompraCuota $compraCuota)
    {
        $request->validate([
            'compra_credito_id' => 'required|exists:compras_credito,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'monto_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $compraCuota->update($request->all());

        return response()->json($compraCuota);
    }

    public function destroy(CompraCuota $compraCuota)
    {
        $compraCuota->delete();
        return response()->json(null, 204);
    }
}
