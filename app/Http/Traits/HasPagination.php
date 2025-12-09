<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

trait HasPagination
{
    /**
     * Aplica búsqueda a la query
     */
    protected function applySearch(Builder $query, Request $request, array $searchableFields): Builder
    {
        if (!$request->has('search') || !$request->search) {
            return $query;
        }

        $search = trim($request->search);
        if (empty($search)) {
            return $query;
        }
        
        return $query->where(function($q) use ($search, $searchableFields) {
            foreach ($searchableFields as $field) {
                try {
                    if (strpos($field, '.') !== false) {
                        // Relación: campo.relacion.campo
                        $parts = explode('.', $field);
                        $relation = $parts[0];
                        $relationField = $parts[1];
                        
                        // Verificar si es un campo numérico en la relación
                        if (in_array($relationField, ['id'])) {
                            if (is_numeric($search)) {
                                $q->orWhereHas($relation, function($subQ) use ($search, $relationField) {
                                    $subQ->where($relationField, $search);
                                });
                            }
                        } else {
                            $q->orWhereHas($relation, function($subQ) use ($search, $relationField) {
                                $subQ->where($relationField, 'like', "%{$search}%");
                            });
                        }
                    } else {
                        // Campo directo
                        // Verificar si es un campo numérico
                        if (in_array($field, ['id']) && is_numeric($search)) {
                            $q->orWhere($field, $search);
                        } else {
                            // Usar LIKE solo para campos de texto
                            $q->orWhere($field, 'like', "%{$search}%");
                        }
                    }
                } catch (\Exception $e) {
                    // Si hay un error con una relación, simplemente la omitimos
                    \Log::warning("Error al buscar en campo {$field}: " . $e->getMessage());
                    continue;
                }
            }
        });
    }

    /**
     * Aplica ordenamiento a la query
     */
    protected function applySorting(Builder $query, Request $request, array $sortableFields = [], string $defaultSort = 'id', string $defaultOrder = 'desc'): Builder
    {
        $sortBy = $request->get('sort_by', $defaultSort);
        $sortOrder = $request->get('sort_order', $defaultOrder);
        
        // Validar campos ordenables si se proporcionan
        if (!empty($sortableFields) && !in_array($sortBy, $sortableFields)) {
            $sortBy = $defaultSort;
        }
        
        // Validar orden
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = $defaultOrder;
        }
        
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Aplica paginación y retorna respuesta
     */
    protected function paginateResponse(Builder $query, Request $request, int $defaultPerPage = 15, int $maxPerPage = 100)
    {
        try {
            if ($request->has('per_page') || $request->has('page')) {
                $perPage = min((int)$request->get('per_page', $defaultPerPage), $maxPerPage);
                $perPage = max(1, $perPage); // Asegurar que sea al menos 1
                
                $paginated = $query->paginate($perPage);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $paginated->items(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'from' => $paginated->firstItem(),
                        'to' => $paginated->lastItem(),
                    ]
                ]);
            }
            
            // Sin paginación (compatibilidad)
            $items = $query->get();
            return response()->json(['data' => $items]);
        } catch (\Exception $e) {
            \Log::error('Error en paginateResponse', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-lanzar para que el controlador lo maneje
        }
    }
}

