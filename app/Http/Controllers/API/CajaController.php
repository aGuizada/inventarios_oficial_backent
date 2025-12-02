<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function index()
    {
        $cajas = Caja::with(['sucursal', 'user'])->get();
        return response()->json($cajas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'user_id' => 'required|exists:users,id',
            'fecha_apertura' => 'required|date',
            'fecha_cierre' => 'nullable|date',
            'saldo_inicial' => 'required|numeric',
            'depositos' => 'nullable|numeric',
            'salidas' => 'nullable|numeric',
            'ventas' => 'nullable|numeric',
            'ventas_contado' => 'nullable|numeric',
            'ventas_credito' => 'nullable|numeric',
            'pagos_efectivo' => 'nullable|numeric',
            'pagos_qr' => 'nullable|numeric',
            'pagos_transferencia' => 'nullable|numeric',
            'cuotas_ventas_credito' => 'nullable|numeric',
            'compras_contado' => 'nullable|numeric',
            'compras_credito' => 'nullable|numeric',
            'saldo_faltante' => 'nullable|numeric',
            'saldo_caja' => 'nullable|numeric',
            'estado' => 'boolean',
        ]);

        $caja = Caja::create($request->all());

        return response()->json($caja, 201);
    }

    public function show(Caja $caja)
    {
        $caja->load(['sucursal', 'user']);
        return response()->json($caja);
    }

    public function update(Request $request, Caja $caja)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'user_id' => 'required|exists:users,id',
            'fecha_apertura' => 'required|date',
            'fecha_cierre' => 'nullable|date',
            'saldo_inicial' => 'required|numeric',
            'depositos' => 'nullable|numeric',
            'salidas' => 'nullable|numeric',
            'ventas' => 'nullable|numeric',
            'ventas_contado' => 'nullable|numeric',
            'ventas_credito' => 'nullable|numeric',
            'pagos_efectivo' => 'nullable|numeric',
            'pagos_qr' => 'nullable|numeric',
            'pagos_transferencia' => 'nullable|numeric',
            'cuotas_ventas_credito' => 'nullable|numeric',
            'compras_contado' => 'nullable|numeric',
            'compras_credito' => 'nullable|numeric',
            'saldo_faltante' => 'nullable|numeric',
            'saldo_caja' => 'nullable|numeric',
            'estado' => 'boolean',
        ]);

        $caja->update($request->all());

        return response()->json($caja);
    }

    public function destroy(Caja $caja)
    {
        $caja->delete();
        return response()->json(null, 204);
    }
}
