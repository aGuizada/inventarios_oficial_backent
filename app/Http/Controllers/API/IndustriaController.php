<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Industria;
use Illuminate\Http\Request;

class IndustriaController extends Controller
{
    public function index()
    {
        $industrias = Industria::all();
        return response()->json($industrias);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:industrias',
            'estado' => 'boolean',
        ]);

        $industria = Industria::create($request->all());

        return response()->json($industria, 201);
    }

    public function show(Industria $industria)
    {
        return response()->json($industria);
    }

    public function update(Request $request, Industria $industria)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:industrias,nombre,' . $industria->id,
            'estado' => 'boolean',
        ]);

        $industria->update($request->all());

        return response()->json($industria);
    }

    public function destroy(Industria $industria)
    {
        $industria->delete();
        return response()->json(null, 204);
    }
}
