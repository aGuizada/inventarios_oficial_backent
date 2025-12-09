<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Cliente::query();
        
        // Campos buscables: nombre, teléfono, email, número de documento, dirección
        $searchableFields = [
            'nombre',
            'telefono',
            'email',
            'num_documento',
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
            'tipo_cliente' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $cliente = Cliente::create($request->all());

        return response()->json($cliente, 201);
    }

    public function show(Cliente $cliente)
    {
        return response()->json($cliente);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_cliente' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $cliente->update($request->all());

        return response()->json($cliente);
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return response()->json(null, 204);
    }
}
