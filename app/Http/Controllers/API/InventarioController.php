<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Inventario;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Inventario::with(['almacen', 'articulo']);

        $searchableFields = [
            'id',
            'cantidad',
            'saldo_stock',
            'ubicacion',
            'articulo.codigo',
            'articulo.nombre',
            'almacen.nombre_almacen'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'cantidad', 'saldo_stock', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'articulo_id' => 'required|exists:articulos,id',
            'cantidad' => 'required|integer',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $inventario = Inventario::create($request->all());

        return response()->json($inventario, 201);
    }

    public function show(Inventario $inventario)
    {
        $inventario->load(['almacen', 'articulo']);
        return response()->json($inventario);
    }

    public function update(Request $request, Inventario $inventario)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'articulo_id' => 'required|exists:articulos,id',
            'cantidad' => 'required|integer',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $inventario->update($request->all());

        return response()->json($inventario);
    }

    public function destroy(Inventario $inventario)
    {
        $inventario->delete();
        return response()->json(null, 204);
    }
}
