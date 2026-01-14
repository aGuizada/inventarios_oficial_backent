<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Industria;
use Illuminate\Http\Request;

class IndustriaController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        try {
            $query = Industria::query();

            $searchableFields = [
                'id',
                'nombre'
            ];

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'nombre', 'created_at'], 'id', 'desc');

            return $this->paginateResponse($query, $request, 15, 100);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ]
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:industrias',
            'estado' => 'boolean',
        ]);

        $industria = Industria::create($request->all());

        return response()->json($industria, 201);
    }

    public function show(Industria $industria)
    {
        return response()->json($industria);
    }

    public function update(Request $request, Industria $industria)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:industrias,nombre,' . $industria->id,
            'estado' => 'boolean',
        ]);

        $camposPermitidos = ['nombre', 'estado'];
        $industria->update($request->only($camposPermitidos));

        return response()->json($industria);
    }

    public function destroy(Industria $industria)
    {
        $industria->delete();
        return response()->json(null, 204);
    }
}
