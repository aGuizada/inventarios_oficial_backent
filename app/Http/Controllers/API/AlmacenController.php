<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    public function index()
    {
        $almacenes = Almacen::with('sucursal')->get();
        return response()->json($almacenes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre' => 'required|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ]);

        $almacen = Almacen::create($request->all());

        return response()->json($almacen, 201);
    }

    public function show(Almacen $almacen)
    {
        $almacen->load('sucursal');
        return response()->json($almacen);
    }

    public function update(Request $request, Almacen $almacen)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre' => 'required|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ]);

        $almacen->update($request->all());

        return response()->json($almacen);
    }

    public function destroy(Almacen $almacen)
    {
        $almacen->delete();
        return response()->json(null, 204);
    }
}
