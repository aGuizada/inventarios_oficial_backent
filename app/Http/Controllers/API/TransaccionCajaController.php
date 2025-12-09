<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TransaccionCaja;
use App\Models\Caja;
use Illuminate\Http\Request;

class TransaccionCajaController extends Controller
{
    public function index()
    {
        $transacciones = TransaccionCaja::with(['caja', 'user'])->get();
        return response()->json(['data' => $transacciones]);
    }

    public function getByCaja($cajaId)
    {
        $transacciones = TransaccionCaja::where('caja_id', $cajaId)
            ->with(['caja', 'user'])
            ->get();
        return response()->json(['data' => $transacciones]);
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

        // Actualizar los campos depositos o salidas en la caja
        $caja = Caja::find($request->caja_id);
        if ($caja) {
            if ($request->transaccion === 'ingreso') {
                // Sumar al campo depositos
                $caja->depositos = ($caja->depositos ?? 0) + $request->importe;
            } elseif ($request->transaccion === 'egreso') {
                // Sumar al campo salidas
                $caja->salidas = ($caja->salidas ?? 0) + $request->importe;
            }
            $caja->save();
        }

        return response()->json(['data' => $transaccion], 201);
    }

    public function show(TransaccionCaja $transaccionCaja)
    {
        $transaccionCaja->load(['caja', 'user']);
        return response()->json(['data' => $transaccionCaja]);
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

        // Guardar el importe y tipo de transacción anterior para recalcular
        $importeAnterior = $transaccionCaja->importe;
        $transaccionAnterior = $transaccionCaja->transaccion;
        $cajaIdAnterior = $transaccionCaja->caja_id;

        $transaccionCaja->update($request->all());

        // Revertir el cambio anterior en la caja
        $cajaAnterior = Caja::find($cajaIdAnterior);
        if ($cajaAnterior) {
            if ($transaccionAnterior === 'ingreso') {
                $cajaAnterior->depositos = max(0, ($cajaAnterior->depositos ?? 0) - $importeAnterior);
            } elseif ($transaccionAnterior === 'egreso') {
                $cajaAnterior->salidas = max(0, ($cajaAnterior->salidas ?? 0) - $importeAnterior);
            }
            $cajaAnterior->save();
        }

        // Aplicar el nuevo cambio en la caja
        $caja = Caja::find($request->caja_id);
        if ($caja) {
            if ($request->transaccion === 'ingreso') {
                $caja->depositos = ($caja->depositos ?? 0) + $request->importe;
            } elseif ($request->transaccion === 'egreso') {
                $caja->salidas = ($caja->salidas ?? 0) + $request->importe;
            }
            $caja->save();
        }

        return response()->json(['data' => $transaccionCaja]);
    }

    public function destroy(TransaccionCaja $transaccionCaja)
    {
        // Antes de eliminar, revertir el cambio en la caja
        $caja = Caja::find($transaccionCaja->caja_id);
        if ($caja) {
            if ($transaccionCaja->transaccion === 'ingreso') {
                $caja->depositos = max(0, ($caja->depositos ?? 0) - $transaccionCaja->importe);
            } elseif ($transaccionCaja->transaccion === 'egreso') {
                $caja->salidas = max(0, ($caja->salidas ?? 0) - $transaccionCaja->importe);
            }
            $caja->save();
        }

        $transaccionCaja->delete();
        return response()->json(null, 204);
    }
}
