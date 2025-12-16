<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Sucursal::with('empresa');

        $searchableFields = [
            'id',
            'nombre',
            'codigoSucursal',
            'direccion',
            'correo',
            'telefono',
            'departamento',
            'responsable',
            'empresa.nombre'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'nombre', 'codigoSucursal', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'codigoSucursal' => 'required|string|max:50|unique:sucursales',
            'direccion' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:100',
            'estado' => 'nullable|boolean',
            'responsable' => 'nullable|string|max:255',
        ]);

        // Convertir estado a booleano si viene como string
        $data = $request->all();
        if (isset($data['estado'])) {
            if (is_string($data['estado'])) {
                $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($data['estado'])) {
                $data['estado'] = (bool) $data['estado'];
            }
        }

        $sucursal = Sucursal::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Sucursal creada exitosamente',
            'data' => Sucursal::with('empresa')->find($sucursal->id)
        ], 201);
    }

    public function show($id)
    {
        $sucursal = Sucursal::with('empresa')->find($id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        return response()->json($sucursal);
    }

    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'codigoSucursal' => 'required|string|max:50|unique:sucursales,codigoSucursal,' . $id,
            'direccion' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:100',
            'estado' => 'nullable|boolean',
            'responsable' => 'nullable|string|max:255',
        ]);

        // Convertir estado a booleano si viene como string
        $data = $request->all();
        if (isset($data['estado'])) {
            if (is_string($data['estado'])) {
                $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($data['estado'])) {
                $data['estado'] = (bool) $data['estado'];
            }
        }

        $sucursal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Sucursal actualizada exitosamente',
            'data' => Sucursal::with('empresa')->find($id)
        ]);
    }

    public function destroy($id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $sucursal->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Sucursal eliminada exitosamente'
        ], 200);
    }
}
