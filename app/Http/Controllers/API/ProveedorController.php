<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Proveedor::query();
        
        // Campos buscables: nombre, teléfono, email, NIT, dirección
        $searchableFields = [
            'nombre',
            'telefono',
            'email',
            'nit',
            'direccion'
        ];
        
        // Aplicar búsqueda
        $query = $this->applySearch($query, $request, $searchableFields);
        
        // Campos ordenables
        $sortableFields = ['id', 'nombre', 'email', 'telefono', 'created_at'];
        
        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');
        
        // Aplicar paginación
        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_proveedor' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $proveedor = Proveedor::create($request->all());

        return response()->json($proveedor, 201);
    }

    public function show(Proveedor $proveedor)
    {
        return response()->json($proveedor);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_proveedor' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $proveedor->update($request->all());

        return response()->json($proveedor);
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete();
        return response()->json(null, 204);
    }
}
