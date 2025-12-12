<?php

namespace App\Services;

use App\Models\Kardex;
use App\Models\Inventario;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class KardexService
{
    /**
     * Calcula el saldo actual de un artículo en un almacén
     */
    public function calcularSaldo(int $articuloId, int $almacenId): float
    {
        $ultimoMovimiento = Kardex::where('articulo_id', $articuloId)
            ->where('almacen_id', $almacenId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $ultimoMovimiento ? $ultimoMovimiento->cantidad_saldo : 0;
    }

    /**
     * Registra un movimiento en el kardex
     * @throws Exception
     */
    public function registrarMovimiento(array $datos): Kardex
    {
        DB::beginTransaction();
        try {
            // Validar datos requeridos
            $this->validarDatos($datos);

            // Calcular saldo anterior
            $saldoAnterior = $this->calcularSaldo($datos['articulo_id'], $datos['almacen_id']);

            $cantidadEntrada = $datos['cantidad_entrada'] ?? 0;
            $cantidadSalida = $datos['cantidad_salida'] ?? 0;
            $nuevoSaldo = $saldoAnterior + $cantidadEntrada - $cantidadSalida;

            // Validar saldo no negativo
            if ($nuevoSaldo < 0) {
                throw new Exception("El movimiento dejaría un saldo negativo. Stock disponible: {$saldoAnterior}");
            }

            // Actualizar o crear inventario
            $this->actualizarInventario($datos['articulo_id'], $datos['almacen_id'], $cantidadEntrada, $cantidadSalida);

            // Actualizar stock del artículo
            $this->actualizarStockArticulo($datos['articulo_id'], $cantidadEntrada, $cantidadSalida);

            // Calcular costos y precios
            $costoTotal = ($cantidadEntrada + $cantidadSalida) * ($datos['costo_unitario'] ?? 0);
            $precioTotal = ($cantidadEntrada + $cantidadSalida) * ($datos['precio_unitario'] ?? 0);

            // Crear registro kardex
            $kardex = Kardex::create([
                'fecha' => $datos['fecha'] ?? Carbon::now(),
                'tipo_movimiento' => $datos['tipo_movimiento'],
                'documento_tipo' => $datos['documento_tipo'] ?? 'manual',
                'documento_numero' => $datos['documento_numero'] ?? $this->generarNumeroDocumento($datos['tipo_movimiento']),
                'articulo_id' => $datos['articulo_id'],
                'almacen_id' => $datos['almacen_id'],
                'cantidad_entrada' => $cantidadEntrada,
                'cantidad_salida' => $cantidadSalida,
                'cantidad_saldo' => $nuevoSaldo,
                'costo_unitario' => $datos['costo_unitario'] ?? 0,
                'costo_total' => $costoTotal,
                'precio_unitario' => $datos['precio_unitario'] ?? 0,
                'precio_total' => $precioTotal,
                'observaciones' => $datos['observaciones'] ?? null,
                'usuario_id' => $datos['usuario_id'] ?? auth()->id() ?? 1,
                'compra_id' => $datos['compra_id'] ?? null,
                'venta_id' => $datos['venta_id'] ?? null,
                'traspaso_id' => $datos['traspaso_id'] ?? null,
            ]);

            DB::commit();
            return $kardex->load(['articulo', 'almacen', 'usuario']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene resumen con KPIs del kardex
     */
    public function obtenerResumen(array $filtros = []): array
    {
        $query = Kardex::query();
        $query = $this->aplicarFiltros($query, $filtros);

        // Total de movimientos
        $totalMovimientos = $query->count();

        // Totales por tipo
        $totalEntradas = $query->sum('cantidad_entrada');
        $totalSalidas = $query->sum('cantidad_salida');

        // Totales monetarios
        $totalCostos = $query->sum('costo_total');
        $totalVentas = $query->sum('precio_total');

        // Artículos únicos
        $articulosUnicos = $query->distinct('articulo_id')->count('articulo_id');

        // Movimientos por tipo
        $movimientosPorTipo = Kardex::select('tipo_movimiento', DB::raw('COUNT(*) as cantidad'))
            ->when(!empty($filtros), fn($q) => $this->aplicarFiltros($q, $filtros))
            ->groupBy('tipo_movimiento')
            ->get();

        return [
            'total_movimientos' => $totalMovimientos,
            'total_entradas' => round($totalEntradas, 2),
            'total_salidas' => round($totalSalidas, 2),
            'saldo_neto' => round($totalEntradas - $totalSalidas, 2),
            'total_costos' => round($totalCostos, 2),
            'total_ventas' => round($totalVentas, 2),
            'margen' => $totalVentas > 0 ? round((($totalVentas - $totalCostos) / $totalVentas) * 100, 2) : 0,
            'articulos_unicos' => $articulosUnicos,
            'movimientos_por_tipo' => $movimientosPorTipo
        ];
    }

    /**
     * Obtiene kardex valorado (con precios)
     */
    public function obtenerKardexValorado(array $filtros = [], int $perPage = 20)
    {
        $query = Kardex::with(['articulo', 'almacen', 'usuario'])
            ->select([
                'kardex.*',
                DB::raw('(cantidad_entrada + cantidad_salida) as cantidad_total'),
                DB::raw('CASE 
                    WHEN cantidad_entrada > 0 THEN costo_total 
                    WHEN cantidad_salida > 0 THEN precio_total 
                    ELSE 0 
                END as valor_movimiento')
            ]);

        $query = $this->aplicarFiltros($query, $filtros);
        $query->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Genera reporte detallado por artículo
     */
    public function generarReportePorArticulo(int $articuloId, array $filtros = []): array
    {
        $articulo = Articulo::with('categoria', 'medida')->findOrFail($articuloId);

        $query = Kardex::with(['almacen', 'usuario'])
            ->where('articulo_id', $articuloId);

        $query = $this->aplicarFiltros($query, $filtros);

        $movimientos = $query->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Cálculos
        $saldoInicial = $filtros['fecha_desde'] ?? null
            ? $this->calcularSaldoEnFecha($articuloId, $filtros['almacen_id'] ?? null, $filtros['fecha_desde'])
            : 0;

        $totalEntradas = $movimientos->sum('cantidad_entrada');
        $totalSalidas = $movimientos->sum('cantidad_salida');
        $saldoFinal = $saldoInicial + $totalEntradas - $totalSalidas;

        return [
            'articulo' => $articulo,
            'periodo' => [
                'fecha_desde' => $filtros['fecha_desde'] ?? null,
                'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            ],
            'saldo_inicial' => round($saldoInicial, 2),
            'total_entradas' => round($totalEntradas, 2),
            'total_salidas' => round($totalSalidas, 2),
            'saldo_final' => round($saldoFinal, 2),
            'movimientos' => $movimientos,
            'estadisticas' => [
                'total_movimientos' => $movimientos->count(),
                'valor_entradas' => round($movimientos->sum('costo_total'), 2),
                'valor_salidas' => round($movimientos->sum('precio_total'), 2),
            ]
        ];
    }

    /**
     * Recalcula todos los saldos del kardex (útil para correcciones)
     */
    public function recalcularKardex(int $articuloId, int $almacenId): void
    {
        DB::beginTransaction();
        try {
            $movimientos = Kardex::where('articulo_id', $articuloId)
                ->where('almacen_id', $almacenId)
                ->orderBy('fecha', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $saldo = 0;
            foreach ($movimientos as $movimiento) {
                $saldo += $movimiento->cantidad_entrada - $movimiento->cantidad_salida;
                $movimiento->cantidad_saldo = $saldo;
                $movimiento->save();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ========== Métodos Privados ==========

    private function validarDatos(array $datos): void
    {
        $requeridos = ['articulo_id', 'almacen_id', 'tipo_movimiento'];
        foreach ($requeridos as $campo) {
            if (!isset($datos[$campo])) {
                throw new Exception("El campo {$campo} es requerido");
            }
        }

        // Validar que tenga entrada o salida
        if (empty($datos['cantidad_entrada']) && empty($datos['cantidad_salida'])) {
            throw new Exception("Debe especificar cantidad_entrada o cantidad_salida");
        }
    }

    private function actualizarInventario(int $articuloId, int $almacenId, float $entrada, float $salida): void
    {
        $inventario = Inventario::where('articulo_id', $articuloId)
            ->where('almacen_id', $almacenId)
            ->first();

        $diferencia = $entrada - $salida;

        if ($inventario) {
            $inventario->cantidad += $diferencia;
            $inventario->saldo_stock += $diferencia;
            $inventario->save();
        } else {
            Inventario::create([
                'articulo_id' => $articuloId,
                'almacen_id' => $almacenId,
                'cantidad' => $diferencia,
                'saldo_stock' => $diferencia,
                'fecha_vencimiento' => '2099-12-31'
            ]);
        }
    }

    private function actualizarStockArticulo(int $articuloId, float $entrada, float $salida): void
    {
        $articulo = Articulo::find($articuloId);
        if ($articulo) {
            $articulo->stock += ($entrada - $salida);
            $articulo->save();
        }
    }

    private function generarNumeroDocumento(string $tipoMovimiento): string
    {
        $prefijo = match ($tipoMovimiento) {
            'ajuste' => 'AJ',
            'compra' => 'CO',
            'venta' => 'VE',
            'traspaso_entrada' => 'TE',
            'traspaso_salida' => 'TS',
            default => 'MV'
        };

        return $prefijo . '-' . time() . '-' . rand(100, 999);
    }

    private function aplicarFiltros($query, array $filtros)
    {
        if (!empty($filtros['articulo_id'])) {
            $query->where('articulo_id', $filtros['articulo_id']);
        }

        if (!empty($filtros['almacen_id'])) {
            $query->where('almacen_id', $filtros['almacen_id']);
        }

        if (!empty($filtros['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $filtros['tipo_movimiento']);
        }

        if (!empty($filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $filtros['fecha_hasta']);
        }

        return $query;
    }

    private function calcularSaldoEnFecha(int $articuloId, ?int $almacenId, string $fecha): float
    {
        $query = Kardex::where('articulo_id', $articuloId)
            ->where('fecha', '<', $fecha);

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        $movimientos = $query->get();

        $saldo = 0;
        foreach ($movimientos as $mov) {
            $saldo += $mov->cantidad_entrada - $mov->cantidad_salida;
        }

        return $saldo;
    }
}
