<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Moneda;
use Illuminate\Http\Request;

class MonedaController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Moneda::with('empresa');

        $searchableFields = [
            'id',
            'nombre',
            'pais',
            'simbolo',
            'empresa.nombre'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'nombre', 'tipo_cambio', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:50',
            'pais' => 'nullable|string|max:50',
            'simbolo' => 'nullable|string|max:10',
            'tipo_cambio' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $moneda = Moneda::create($request->all());
        $moneda->load('empresa');

        return response()->json($moneda, 201);
    }

    public function show(Moneda $moneda)
    {
        $moneda->load('empresa');
        return response()->json($moneda);
    }

    public function update(Request $request, Moneda $moneda)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:50',
            'pais' => 'nullable|string|max:50',
            'simbolo' => 'nullable|string|max:10',
            'tipo_cambio' => 'required|numeric',
            'estado' => 'boolean',
        ]);

        $moneda->update($request->all());
        $moneda->load('empresa');

        return response()->json($moneda);
    }

    public function destroy(Moneda $moneda)
    {
        $moneda->delete();
        return response()->json(null, 204);
    }
}
