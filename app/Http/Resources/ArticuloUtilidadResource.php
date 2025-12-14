<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear datos de utilidad por artículo
 * Incluye métricas de ventas, costos y márgenes de ganancia
 */
class ArticuloUtilidadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'articulo_id' => $this->articulo_id,
            'codigo' => $this->codigo ?? 'N/A',
            'nombre' => $this->nombre ?? 'Sin nombre',

            // Métricas de ventas
            'cantidad_vendida' => (int) ($this->cantidad_vendida ?? 0),
            'total_ventas' => round((float) ($this->total_ventas ?? 0), 2),

            // Métricas de costos
            'costo_total' => round((float) ($this->costo_total ?? 0), 2),

            // Métricas de utilidad
            'utilidad_bruta' => round((float) ($this->utilidad_bruta ?? 0), 2),
            'margen_porcentaje' => round((float) ($this->margen_porcentaje ?? 0), 2),

            // Indicador de rentabilidad
            'rentabilidad' => $this->clasificarRentabilidad(),
        ];
    }

    /**
     * Clasifica la rentabilidad del artículo según el margen
     */
    private function clasificarRentabilidad(): string
    {
        $margen = (float) ($this->margen_porcentaje ?? 0);

        if ($margen >= 30) {
            return 'alta';
        } elseif ($margen >= 15) {
            return 'media';
        } elseif ($margen >= 5) {
            return 'baja';
        } else {
            return 'muy_baja';
        }
    }

    /**
     * Metadata adicional para la colección
     */
    public function with(Request $request): array
    {
        return [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }
}
