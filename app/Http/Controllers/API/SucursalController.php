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

    public function show($id)
    {
        $sucursal = Sucursal::with('empresa')->find($id);
        
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }
        
        return response()->json($sucursal);
    }

    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'codigoSucursal' => 'required|string|max:50|unique:sucursales,codigoSucursal,' . $id,
            'direccion' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:100',
            'estado' => 'boolean',
            'responsable' => 'nullable|string|max:255',
        ]);

        $sucursal->update($request->all());

        return response()->json(Sucursal::with('empresa')->find($id));
    }

    public function destroy($id)
    {
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }
        
        $sucursal->delete();
        return response()->json(null, 204);
    }
}
