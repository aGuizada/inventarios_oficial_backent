<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Medida;
use Illuminate\Http\Request;

class MedidaController extends Controller
{
    public function index()
    {
        $medidas = Medida::all();
        return response()->json($medidas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_medida' => 'required|string|max:100|unique:medidas',
            'estado' => 'boolean',
        ]);

        $medida = Medida::create($request->all());

        return response()->json($medida, 201);
    }

    public function show(Medida $medida)
    {
        return response()->json($medida);
    }

    public function update(Request $request, Medida $medida)
    {
        $request->validate([
            'nombre_medida' => 'required|string|max:100|unique:medidas,nombre_medida,' . $medida->id,
            'estado' => 'boolean',
        ]);

        $medida->update($request->all());

        return response()->json($medida);
    }

    public function destroy(Medida $medida)
    {
        $medida->delete();
        return response()->json(null, 204);
    }
}
