<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Marca::query();

        $searchableFields = [
            'id',
            'nombre'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'nombre', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:marcas',
            'estado' => 'boolean',
        ]);

        $marca = Marca::create($request->all());

        return response()->json($marca, 201);
    }

    public function show(Marca $marca)
    {
        return response()->json($marca);
    }

    public function update(Request $request, Marca $marca)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:marcas,nombre,' . $marca->id,
            'estado' => 'boolean',
        ]);

        $marca->update($request->all());

        return response()->json($marca);
    }

    public function destroy(Marca $marca)
    {
        $marca->delete();
        return response()->json(null, 204);
    }
}
