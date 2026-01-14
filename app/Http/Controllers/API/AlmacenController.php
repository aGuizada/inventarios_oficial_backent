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
        try {
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
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre_almacen' => 'required|string|max:100',
            'ubicacion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
            'estado' => 'nullable|boolean',
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

        $almacen = Almacen::create($data);
        $almacen->load('sucursal');

        return response()->json([
            'success' => true,
            'message' => 'Almacén creado exitosamente',
            'data' => $almacen
        ], 201);
    }

    public function show(Almacen $almacen)
    {
        $almacen->load('sucursal');
        return response()->json($almacen);
    }

    public function update(Request $request, Almacen $almacen)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre_almacen' => 'required|string|max:100',
            'ubicacion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
            'estado' => 'nullable|boolean',
        ]);

        // IMPORTANTE: Solo actualizar campos que se enviaron explícitamente
        // Esto preserva los datos existentes del servidor que no se están actualizando
        $camposPermitidos = ['sucursal_id', 'nombre_almacen', 'ubicacion', 'telefono', 'estado'];
        $data = $request->only($camposPermitidos);

        // Convertir estado a booleano si viene como string (solo si se envió)
        if (isset($data['estado'])) {
            if (is_string($data['estado'])) {
                $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($data['estado'])) {
                $data['estado'] = (bool) $data['estado'];
            }
        }

        $almacen->update($data);
        $almacen->load('sucursal');

        return response()->json([
            'success' => true,
            'message' => 'Almacén actualizado exitosamente',
            'data' => $almacen
        ]);
    }

    public function destroy($id)
    {
        try {
            $almacen = Almacen::find($id);

            if (!$almacen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Almacén no encontrado'
                ], 404);
            }

            $almacenId = $almacen->id;
            $almacen->delete();

            // Verificar que realmente se eliminó
            $verificar = Almacen::find($almacenId);
            if ($verificar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: El almacén no se pudo eliminar'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Almacén eliminado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el almacén: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle almacen status (active/inactive)
     */
    public function toggleStatus(Almacen $almacen)
    {
        $almacen->estado = !$almacen->estado;
        $almacen->save();
        $almacen->load('sucursal');

        return response()->json([
            'success' => true,
            'message' => $almacen->estado ? 'Almacén activado' : 'Almacén desactivado',
            'data' => $almacen
        ]);
    }
}
