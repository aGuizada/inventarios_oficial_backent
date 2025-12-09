<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Almacen::with('sucursal');

        $searchableFields = [
            'id',
            'nombre_almacen',
            'ubicacion',
            'telefono',
            'sucursal.nombre'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'nombre_almacen', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre_almacen' => 'required|string|max:100',
            'ubicacion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
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
            'sucursal_id' => 'sometimes|required|exists:sucursales,id',
            'nombre_almacen' => 'sometimes|required|string|max:100',
            'ubicacion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
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
