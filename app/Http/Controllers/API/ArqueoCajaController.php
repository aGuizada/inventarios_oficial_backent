<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ArqueoCaja;
use Illuminate\Http\Request;

class ArqueoCajaController extends Controller
{
    public function index()
    {
        $arqueos = ArqueoCaja::with(['caja', 'user'])->get();
        return response()->json(['data' => $arqueos]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'user_id' => 'required|exists:users,id',
            'billete200' => 'nullable|integer',
            'billete100' => 'nullable|integer',
            'billete50' => 'nullable|integer',
            'billete20' => 'nullable|integer',
            'billete10' => 'nullable|integer',
            'moneda5' => 'nullable|integer',
            'moneda2' => 'nullable|integer',
            'moneda1' => 'nullable|integer',
            'moneda050' => 'nullable|integer',
            'moneda020' => 'nullable|integer',
            'moneda010' => 'nullable|integer',
            'total_efectivo' => 'required|numeric',
        ]);

        $arqueo = ArqueoCaja::create($request->all());

        return response()->json($arqueo, 201);
    }

    public function show(ArqueoCaja $arqueoCaja)
    {
        $arqueoCaja->load(['caja', 'user']);
        return response()->json($arqueoCaja);
    }

    public function update(Request $request, ArqueoCaja $arqueoCaja)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'user_id' => 'required|exists:users,id',
            'billete200' => 'nullable|integer',
            'billete100' => 'nullable|integer',
            'billete50' => 'nullable|integer',
            'billete20' => 'nullable|integer',
            'billete10' => 'nullable|integer',
            'moneda5' => 'nullable|integer',
            'moneda2' => 'nullable|integer',
            'moneda1' => 'nullable|integer',
            'moneda050' => 'nullable|integer',
            'moneda020' => 'nullable|integer',
            'moneda010' => 'nullable|integer',
            'total_efectivo' => 'required|numeric',
        ]);

        $arqueoCaja->update($request->all());

        return response()->json($arqueoCaja);
    }

    public function destroy(ArqueoCaja $arqueoCaja)
    {
        $arqueoCaja->delete();
        return response()->json(null, 204);
    }
}
