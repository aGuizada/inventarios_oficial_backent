<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TipoVenta;
use Illuminate\Http\Request;

class TipoVentaController extends Controller
{
    public function index()
    {
        $tipoVentas = TipoVenta::all();
        return response()->json($tipoVentas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_tipo_ventas' => 'required|string|max:100|unique:tipo_ventas',
        ]);

        $tipoVenta = TipoVenta::create($request->all());

        return response()->json($tipoVenta, 201);
    }

    public function show(TipoVenta $tipoVenta)
    {
        return response()->json($tipoVenta);
    }

    public function update(Request $request, TipoVenta $tipoVenta)
    {
        $request->validate([
            'nombre_tipo_ventas' => 'required|string|max:100|unique:tipo_ventas,nombre_tipo_ventas,' . $tipoVenta->id,
        ]);

        $tipoVenta->update($request->all());

        return response()->json($tipoVenta);
    }

    public function destroy(TipoVenta $tipoVenta)
    {
        $tipoVenta->delete();
        return response()->json(null, 204);
    }
}
