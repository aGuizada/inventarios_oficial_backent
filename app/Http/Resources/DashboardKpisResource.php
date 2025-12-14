<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear KPIs del Dashboard
 * Estandariza la respuesta de métricas clave del negocio
 */
class DashboardKpisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Métricas de Ventas
            'ventas' => [
                'ventas_hoy' => round((float) ($this->resource->ventas_hoy ?? 0), 2),
                'ventas_mes' => round((float) ($this->resource->ventas_mes ?? 0), 2),
                'ventas_mes_anterior' => round((float) ($this->resource->ventas_mes_anterior ?? 0), 2),
                'total_ventas' => round((float) ($this->resource->total_ventas ?? 0), 2),
                'crecimiento_ventas' => round((float) ($this->resource->crecimiento_ventas ?? 0), 2),
            ],

            // Métricas de Inventario
            'inventario' => [
                'productos_bajo_stock' => (int) ($this->resource->productos_bajo_stock ?? 0),
                'productos_agotados' => (int) ($this->resource->productos_agotados ?? 0),
                'valor_total_inventario' => round((float) ($this->resource->valor_total_inventario ?? 0), 2),
            ],

            // Métricas de Compras
            'compras' => [
                'compras_mes' => round((float) ($this->resource->compras_mes ?? 0), 2),
            ],

            // Métricas de Créditos
            'creditos' => [
                'creditos_pendientes' => (int) ($this->resource->creditos_pendientes ?? 0),
                'monto_creditos_pendientes' => round((float) ($this->resource->monto_creditos_pendientes ?? 0), 2),
            ],

            // Análisis
            'analisis' => [
                'margen_bruto' => round((float) ($this->resource->margen_bruto ?? 0), 2),
            ],

            // Filtros aplicados (si existen)
            'filtros_aplicados' => [
                'fecha_inicio' => $this->resource->fecha_inicio ?? null,
                'fecha_fin' => $this->resource->fecha_fin ?? null,
                'periodo' => $this->resource->periodo ?? 'todos',
            ],
        ];
    }

    /**
     * Metadata adicional
     */
    public function with(Request $request): array
    {
        return [
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
            ],
        ];
    }
}
