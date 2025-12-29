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
            return response()->json([
                'success' => false,
                'error' => 'Sucursal no encontrada'
            ], 404);
        }

        // Validar si es la sucursal principal (generalmente la primera, ID 1)
        if ($sucursal->id == 1) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar la sucursal principal del sistema',
                'message' => 'La sucursal principal no puede ser eliminada por razones de integridad del sistema.'
            ], 400);
        }

        // Validar si tiene usuarios asociados
        $usuariosCount = $sucursal->users()->count();
        if ($usuariosCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar la sucursal',
                'message' => "No se puede eliminar esta sucursal porque tiene {$usuariosCount} usuario(s) asociado(s). Por favor, reasigne o elimine los usuarios antes de eliminar la sucursal."
            ], 400);
        }

        // Validar si tiene almacenes asociados
        $almacenesCount = $sucursal->almacenes()->count();
        if ($almacenesCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar la sucursal',
                'message' => "No se puede eliminar esta sucursal porque tiene {$almacenesCount} almacén(es) asociado(s). Por favor, elimine o reasigne los almacenes antes de eliminar la sucursal."
            ], 400);
        }

        // Validar si tiene cajas asociadas
        $cajasCount = $sucursal->cajas()->count();
        if ($cajasCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar la sucursal',
                'message' => "No se puede eliminar esta sucursal porque tiene {$cajasCount} caja(s) asociada(s). Por favor, elimine o reasigne las cajas antes de eliminar la sucursal."
            ], 400);
        }

        $sucursal->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Sucursal eliminada exitosamente'
        ], 200);
    }
}
