<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionTrabajo;
use Illuminate\Http\Request;

class ConfiguracionTrabajoController extends Controller
{
    public function index()
    {
        $configuraciones = ConfiguracionTrabajo::with(['monedaPrincipal', 'monedaVenta', 'monedaCompra', 'empresa'])->get();
        return response()->json([
            'success' => true,
            'data' => $configuraciones
        ]);
    }

    public function store(Request $request)
    {
        $monedaId = $request->input('moneda_id') ? (int) $request->input('moneda_id') : 1;
        $data = $this->mapRequestToModel($request->all());
        $data['gestion'] = $data['gestion'] ?? date('Y');
        $data['codigo_productos'] = $data['codigo_productos'] ?? 'auto';
        $data['valuacion_inventario'] = $data['valuacion_inventario'] ?? 'PEPS';
        $data['separador_decimales'] = $data['separador_decimales'] ?? '.';
        $data['moneda_principal_id'] = $monedaId;
        $data['moneda_venta_id'] = $monedaId;
        $data['moneda_compra_id'] = $monedaId;

        $configuracion = ConfiguracionTrabajo::create($data);

        return response()->json($configuracion, 201);
    }

    /**
     * Mapea los campos que envía el frontend a las columnas de la tabla.
     */
    private function mapRequestToModel(array $input): array
    {
        $fillable = (new ConfiguracionTrabajo)->getFillable();
        $out = [];

        $map = [
            'moneda_id' => null, // se asigna manualmente a principal/venta/compra
            'empresa_id' => 'empresa_id',
            'backup_automatico' => 'backup_automatico',
            'ruta_backup' => 'ruta_backup',
            'mantener_backups' => null, // no existe en tabla, ignorar
            'frecuencia_backup' => null,
            'mostrar_costo_unitario' => 'mostrar_costo_unitario',
            'mostrar_costo_paquete' => 'mostrar_costo_paquete',
            'mostrar_costo_compra' => 'mostrar_costo_compra',
            'mostrar_precios_adicionales' => 'mostrar_precios_adicionales',
            'mostrar_vencimiento' => 'mostrar_vencimiento',
            'mostrar_stock' => 'mostrar_stock',
        ];

        foreach ($map as $from => $to) {
            if ($to && array_key_exists($from, $input) && in_array($to, $fillable)) {
                $out[$to] = $input[$from];
            }
        }

        foreach ($fillable as $col) {
            if (array_key_exists($col, $input) && !isset($out[$col])) {
                $out[$col] = $input[$col];
            }
        }

        return $out;
    }

    public function show(ConfiguracionTrabajo $configuracionTrabajo)
    {
        $configuracionTrabajo->load(['monedaPrincipal', 'monedaVenta', 'monedaCompra', 'empresa']);
        return response()->json([
            'success' => true,
            'data' => $configuracionTrabajo
        ]);
    }

    public function update(Request $request, ConfiguracionTrabajo $configuracionTrabajo)
    {
        $data = $this->mapRequestToModel($request->all());

        if ($request->has('moneda_id') && $request->input('moneda_id')) {
            $mid = (int) $request->input('moneda_id');
            $data['moneda_principal_id'] = $mid;
            $data['moneda_venta_id'] = $mid;
            $data['moneda_compra_id'] = $mid;
        }

        $configuracionTrabajo->update($data);

        return response()->json($configuracionTrabajo);
    }

    public function destroy(ConfiguracionTrabajo $configuracionTrabajo)
    {
        $configuracionTrabajo->delete();
        return response()->json(null, 204);
    }
}
