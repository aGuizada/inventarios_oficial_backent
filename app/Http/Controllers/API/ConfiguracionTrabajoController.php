<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionTrabajo;
use Illuminate\Http\Request;

class ConfiguracionTrabajoController extends Controller
{
    public function index()
    {
        $configuraciones = ConfiguracionTrabajo::with(['monedaPrincipal', 'monedaVenta', 'monedaCompra', 'empresa'])->get();
        return response()->json([
            'success' => true,
            'data' => $configuraciones
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_empresa' => 'nullable|string|max:255',
            'nit' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'moneda_id' => 'nullable|exists:monedas,id',
            'iva' => 'nullable|numeric',
            'mensaje_ticket' => 'nullable|string',
        ]);

        $configuracion = ConfiguracionTrabajo::create($request->all());

        return response()->json($configuracion, 201);
    }

    public function show(ConfiguracionTrabajo $configuracionTrabajo)
    {
        $configuracionTrabajo->load(['monedaPrincipal', 'monedaVenta', 'monedaCompra', 'empresa']);
        return response()->json([
            'success' => true,
            'data' => $configuracionTrabajo
        ]);
    }

    public function update(Request $request, ConfiguracionTrabajo $configuracionTrabajo)
    {
        $request->validate([
            'nombre_empresa' => 'nullable|string|max:255',
            'nit' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'moneda_id' => 'nullable|exists:monedas,id',
            'iva' => 'nullable|numeric',
            'mensaje_ticket' => 'nullable|string',
        ]);

        $configuracionTrabajo->update($request->all());

        return response()->json($configuracionTrabajo);
    }

    public function destroy(ConfiguracionTrabajo $configuracionTrabajo)
    {
        $configuracionTrabajo->delete();
        return response()->json(null, 204);
    }
}
