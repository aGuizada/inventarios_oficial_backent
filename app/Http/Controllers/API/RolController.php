<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Rol::query();

        $searchableFields = [
            'id',
            'nombre',
            'descripcion'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'nombre', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
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

        $camposPermitidos = ['nombre', 'descripcion', 'estado'];
        $rol->update($request->only($camposPermitidos));

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
