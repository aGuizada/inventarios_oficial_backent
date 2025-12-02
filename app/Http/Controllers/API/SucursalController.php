<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::with('empresa')->get();
        return response()->json($sucursales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'codigoSucursal' => 'required|string|max:50|unique:sucursales',
            'direccion' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:100',
            'estado' => 'boolean',
            'responsable' => 'nullable|string|max:255',
        ]);

        $sucursal = Sucursal::create($request->all());

        return response()->json($sucursal, 201);
    }

    public function show(Sucursal $sucursal)
    {
        $sucursal->load('empresa');
        return response()->json($sucursal);
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'codigoSucursal' => 'required|string|max:50|unique:sucursales,codigoSucursal,' . $sucursal->id,
            'direccion' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:100',
            'estado' => 'boolean',
            'responsable' => 'nullable|string|max:255',
        ]);

        $sucursal->update($request->all());

        return response()->json($sucursal);
    }

    public function destroy(Sucursal $sucursal)
    {
        $sucursal->delete();
        return response()->json(null, 204);
    }
}
