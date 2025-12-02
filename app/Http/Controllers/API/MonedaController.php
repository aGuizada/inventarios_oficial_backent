<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Moneda;
use Illuminate\Http\Request;

class MonedaController extends Controller
{
    public function index()
    {
        $monedas = Moneda::with('empresa')->get();
        return response()->json($monedas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:50',
            'pais' => 'nullable|string|max:50',
            'simbolo' => 'nullable|string|max:10',
            'tipo_cambio' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $moneda = Moneda::create($request->all());

        return response()->json($moneda, 201);
    }

    public function show(Moneda $moneda)
    {
        $moneda->load('empresa');
        return response()->json($moneda);
    }

    public function update(Request $request, Moneda $moneda)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:50',
            'pais' => 'nullable|string|max:50',
            'simbolo' => 'nullable|string|max:10',
            'tipo_cambio' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $moneda->update($request->all());

        return response()->json($moneda);
    }

    public function destroy(Moneda $moneda)
    {
        $moneda->delete();
        return response()->json(null, 204);
    }
}
