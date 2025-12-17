<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        return response()->json([
            'success' => true,
            'data' => $moneda
        ], 201);
    }

    public function show(Moneda $moneda)
    {
        $moneda->load('empresa');
        return response()->json([
            'success' => true,
            'data' => $moneda
        ]);
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

        // Guardar el tipo de cambio anterior para calcular la proporción
        $tipoCambioAnterior = (float) $moneda->tipo_cambio;
        $nuevoTipoCambio = (float) $request->input('tipo_cambio');

        // Preparar los datos para actualizar, asegurando que estado sea boolean o número
        $data = $request->all();
        
        // Convertir estado a número si viene como boolean
        if (isset($data['estado'])) {
            $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        // Asegurar que tipo_cambio sea numérico
        $data['tipo_cambio'] = $nuevoTipoCambio;

        // Actualizar la moneda
        $moneda->update($data);
        $moneda->load('empresa');

        // Si cambió el tipo de cambio, recalcular precios de todos los productos
        if (abs($tipoCambioAnterior - $nuevoTipoCambio) > 0.0001 && $tipoCambioAnterior > 0) {
            Log::info("Tipo de cambio cambiado de {$tipoCambioAnterior} a {$nuevoTipoCambio} para moneda ID {$moneda->id}");
            $this->recalcularPreciosProductos($moneda->id, $tipoCambioAnterior, $nuevoTipoCambio);
        }

        return response()->json([
            'success' => true,
            'data' => $moneda
        ]);
    }

    /**
     * Recalcula los precios de todos los productos cuando cambia el tipo de cambio
     */
    private function recalcularPreciosProductos(int $monedaId, float $tipoCambioAnterior, float $nuevoTipoCambio): void
    {
        try {
            Log::info("Iniciando recálculo de precios para moneda ID {$monedaId}. Tipo de cambio: {$tipoCambioAnterior} -> {$nuevoTipoCambio}");

            // Calcular la proporción de cambio
            $proporcion = $nuevoTipoCambio / $tipoCambioAnterior;
            
            Log::info("Proporción calculada: {$proporcion}");

            // Obtener la configuración de trabajo para verificar si esta moneda es la principal, de venta o compra
            $configuracion = \App\Models\ConfiguracionTrabajo::first();
            
            // Verificar si esta moneda es la principal, de venta o compra
            $esMonedaRelevante = $configuracion && (
                $configuracion->moneda_principal_id == $monedaId ||
                $configuracion->moneda_venta_id == $monedaId ||
                $configuracion->moneda_compra_id == $monedaId
            );

            if ($esMonedaRelevante) {
                Log::info("Moneda ID {$monedaId} es relevante (principal, venta o compra). Recalculando precios...");
            } else {
                Log::info("Moneda ID {$monedaId} no está configurada como relevante, pero se recalculan precios de todas formas.");
            }

            // Recalcular todos los precios de los productos
            $productosActualizados = DB::table('articulos')->update([
                'precio_costo_unid' => DB::raw("precio_costo_unid * {$proporcion}"),
                'precio_costo_paq' => DB::raw("precio_costo_paq * {$proporcion}"),
                'precio_venta' => DB::raw("precio_venta * {$proporcion}"),
                'precio_uno' => DB::raw("COALESCE(precio_uno, 0) * {$proporcion}"),
                'precio_dos' => DB::raw("COALESCE(precio_dos, 0) * {$proporcion}"),
                'precio_tres' => DB::raw("COALESCE(precio_tres, 0) * {$proporcion}"),
                'precio_cuatro' => DB::raw("COALESCE(precio_cuatro, 0) * {$proporcion}"),
                'costo_compra' => DB::raw("costo_compra * {$proporcion}"),
            ]);

            Log::info("Precios recalculados exitosamente para moneda ID {$monedaId}. Proporción: {$proporcion}. Productos actualizados: {$productosActualizados}");
            
        } catch (\Exception $e) {
            Log::error("Error al recalcular precios de productos: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            // No lanzar excepción para no interrumpir la actualización de la moneda
        }
    }

    public function destroy(Moneda $moneda)
    {
        $moneda->delete();
        return response()->json(null, 204);
    }
}
