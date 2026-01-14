<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Medida;
use Illuminate\Http\Request;

class MedidaController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        try {
            $query = Medida::query();

            $searchableFields = [
                'id',
                'nombre_medida'
            ];

            $query = $this->applySearch($query, $request, $searchableFields);
            $query = $this->applySorting($query, $request, ['id', 'nombre_medida', 'created_at'], 'id', 'desc');

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
            'nombre_medida' => 'required|string|max:100|unique:medidas',
            'estado' => 'boolean',
        ]);

        $medida = Medida::create($request->all());

        return response()->json($medida, 201);
    }

    public function show(Medida $medida)
    {
        return response()->json($medida);
    }

    public function update(Request $request, Medida $medida)
    {
        $request->validate([
            'nombre_medida' => 'required|string|max:100|unique:medidas,nombre_medida,' . $medida->id,
            'estado' => 'boolean',
        ]);

        $camposPermitidos = ['nombre_medida', 'estado'];
        $medida->update($request->only($camposPermitidos));

        return response()->json($medida);
    }

    public function destroy(Medida $medida)
    {
        $medida->delete();
        return response()->json(null, 204);
    }
    public function toggleStatus(Medida $medida)
    {
        $medida->estado = !$medida->estado;
        $medida->save();
        return response()->json([
            'success' => true,
            'message' => $medida->estado ? 'Medida activada' : 'Medida desactivada',
            'data' => $medida
        ]);
    }
}
