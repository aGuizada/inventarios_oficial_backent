<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index()
    {
        $roles = Rol::all();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:50|unique:roles',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ]);

        $rol = Rol::create($request->all());

        return response()->json($rol, 201);
    }

    public function show($id)
    {
        $rol = Rol::find($id);
        
        if (!$rol) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }
        
        return response()->json($rol);
    }

    public function update(Request $request, $id)
    {
        $rol = Rol::find($id);
        
        if (!$rol) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }

        $request->validate([
            'nombre' => 'required|string|max:50|unique:roles,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ]);

        $rol->update($request->all());

        return response()->json($rol);
    }

    public function destroy($id)
    {
        $rol = Rol::find($id);
        
        if (!$rol) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }
        
        $rol->delete();
        return response()->json(null, 204);
    }
}
