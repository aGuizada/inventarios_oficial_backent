<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Precio;
use Illuminate\Http\Request;

class PrecioController extends Controller
{
    public function index()
    {
        $precios = Precio::all();
        return response()->json($precios);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_precio' => 'required|string|max:100',
            'porcentaje' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $precio = Precio::create($request->all());

        return response()->json($precio, 201);
    }

    public function show(Precio $precio)
    {
        return response()->json($precio);
    }

    public function update(Request $request, Precio $precio)
    {
        $request->validate([
            'nombre_precio' => 'required|string|max:100',
            'porcentaje' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $camposPermitidos = ['nombre_precio', 'porcentaje', 'estado'];
        $precio->update($request->only($camposPermitidos));

        return response()->json($precio);
    }

    public function destroy(Precio $precio)
    {
        $precio->delete();
        return response()->json(null, 204);
    }
}
