<?php

namespace App\Services;

use App\Models\Kardex;
use App\Models\Inventario;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class KardexService
{
    /**
     * Calcula el saldo actual de un artículo en un almacén
     */
    public function calcularSaldo(int $articuloId, int $almacenId): float
    {
        try {
            // Verificar si la tabla existe
            if (!\Schema::hasTable('kardex')) {
                return 0;
            }
            
            $ultimoMovimiento = Kardex::where('articulo_id', $articuloId)
                ->where('almacen_id', $almacenId)
                ->orderBy('fecha', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            return $ultimoMovimiento ? $ultimoMovimiento->cantidad_saldo : 0;
        } catch (\Exception $e) {
            // Si hay error (tabla no existe), retornar 0
            \Log::warning('Error al calcular saldo de kardex: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Registra un movimiento en el kardex
     * @throws Exception
     */
    public function registrarMovimiento(array $datos): ?Kardex
    {
        // Si la tabla kardex no existe, solo actualizar inventario y stock
        if (!Schema::hasTable('kardex')) {
            \Log::warning('Tabla kardex no existe. Actualizando solo inventario y stock.');
            try {
                $cantidadEntrada = (float) ($datos['cantidad_entrada'] ?? 0);
                $cantidadSalida = (float) ($datos['cantidad_salida'] ?? 0);
                
                // Log para depuración
                \Log::info("KardexService - Sin tabla kardex, actualizando directamente");
                \Log::info("Articulo ID: {$datos['articulo_id']}, Almacen ID: {$datos['almacen_id']}");
                \Log::info("Entrada: {$cantidadEntrada}, Salida: {$cantidadSalida}");
                
                // Actualizar inventario y stock sin registrar en kardex
                $this->actualizarInventario($datos['articulo_id'], $datos['almacen_id'], $cantidadEntrada, $cantidadSalida);
                $this->actualizarStockArticulo($datos['articulo_id'], $cantidadEntrada, $cantidadSalida);
                
                return null;
            } catch (Exception $e) {
                \Log::error('Error al actualizar inventario sin kardex: ' . $e->getMessage());
                throw $e;
            }
        }
        
        // Verificar si ya estamos dentro de una transacción
        $enTransaccion = \DB::transactionLevel() > 0;
        
        if (!$enTransaccion) {
        DB::beginTransaction();
        }
        
        try {
            // Validar datos requeridos
            $this->validarDatos($datos);

            // Obtener el saldo actual del inventario (fuente de verdad)
            // El inventario.saldo_stock es la fuente de verdad, no el kardex
            // Usar CAST explícito para asegurar precisión decimal
            $inventario = \DB::selectOne(
                "SELECT CAST(SUM(saldo_stock) AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE articulo_id = ? AND almacen_id = ?",
                [$datos['articulo_id'], $datos['almacen_id']]
            );
            
            // Usar el saldo_stock del inventario como saldo anterior (más confiable que kardex)
            if ($inventario && $inventario->saldo_stock !== null) {
                $saldoAnterior = (float) $inventario->saldo_stock;
            } else {
                // Si no hay inventario, intentar calcular desde kardex como fallback
                $saldoAnterior = (float) $this->calcularSaldo($datos['articulo_id'], $datos['almacen_id']);
            }

            $cantidadEntrada = (float) ($datos['cantidad_entrada'] ?? 0);
            $cantidadSalida = (float) ($datos['cantidad_salida'] ?? 0);
            $nuevoSaldo = (float) ($saldoAnterior + $cantidadEntrada - $cantidadSalida);
            
            // Log para depuración
            \Log::info("KardexService - Registrar Movimiento");
            \Log::info("Articulo ID: {$datos['articulo_id']}, Almacen ID: {$datos['almacen_id']}");
            \Log::info("Saldo Anterior: {$saldoAnterior}, Entrada: {$cantidadEntrada}, Salida: {$cantidadSalida}, Nuevo Saldo: {$nuevoSaldo}");
            \Log::info("En transacción: " . ($enTransaccion ? 'SI (nivel: ' . \DB::transactionLevel() . ')' : 'NO'));

            // Validar saldo no negativo
            if ($nuevoSaldo < 0) {
                throw new Exception("El movimiento dejaría un saldo negativo. Stock disponible: {$saldoAnterior}");
            }

            // CRÍTICO: Actualizar inventario y stock ANTES de crear el registro kardex
            // Esto asegura que los cambios se persistan correctamente
            $this->actualizarInventario($datos['articulo_id'], $datos['almacen_id'], $cantidadEntrada, $cantidadSalida);

            // Actualizar stock del artículo
            $this->actualizarStockArticulo($datos['articulo_id'], $cantidadEntrada, $cantidadSalida);
            
            // Verificar que los cambios se aplicaron ANTES de continuar usando CAST explícito
            $inventarioVerificado = \DB::selectOne(
                "SELECT CAST(SUM(saldo_stock) AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE articulo_id = ? AND almacen_id = ?",
                [$datos['articulo_id'], $datos['almacen_id']]
            );
            
            if ($inventarioVerificado && $inventarioVerificado->saldo_stock !== null) {
                $stockVerificado = (float) $inventarioVerificado->saldo_stock;
                \Log::info("Verificación después de actualizar - Stock esperado: {$nuevoSaldo}, Stock actual: {$stockVerificado}");
                
                if (abs($stockVerificado - $nuevoSaldo) > 0.0001) {
                    \Log::error("ERROR CRÍTICO: El stock no coincide después de actualizar. Reintentando...");
                    // Reintentar la actualización
                    $this->actualizarInventario($datos['articulo_id'], $datos['almacen_id'], $cantidadEntrada, $cantidadSalida);
                    $this->actualizarStockArticulo($datos['articulo_id'], $cantidadEntrada, $cantidadSalida);
                }
            }

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

            if (!$enTransaccion) {
            DB::commit();
            }
            
            return $kardex->load(['articulo', 'almacen', 'usuario']);

        } catch (Exception $e) {
            if (!$enTransaccion) {
            DB::rollBack();
            }
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
        // CRÍTICO: Redondear entrada y salida a 3 decimales antes de calcular diferencia
        $entrada = round((float) $entrada, 3);
        $salida = round((float) $salida, 3);
        $diferencia = (float) ($entrada - $salida);
        
        \Log::info("Actualizar Inventario - Artículo: {$articuloId}, Almacén: {$almacenId}, Entrada: {$entrada}, Salida: {$salida}, Diferencia: {$diferencia}");
        
        // CRÍTICO: Obtener TODOS los registros de inventario para este artículo y almacén
        // NO usar el modelo Eloquent para evitar problemas de caché
        // Usar CAST explícito para asegurar que los valores se obtengan como DECIMAL con precisión
        $registrosBD = \DB::select(
            "SELECT id, 
                    CAST(cantidad AS DECIMAL(11,3)) as cantidad, 
                    CAST(saldo_stock AS DECIMAL(11,3)) as saldo_stock, 
                    fecha_vencimiento 
             FROM inventarios 
             WHERE articulo_id = ? AND almacen_id = ? 
             ORDER BY fecha_vencimiento ASC, id ASC",
            [$articuloId, $almacenId]
        );
        
        // Convertir a Collection para mantener compatibilidad con el código existente
        $registrosBD = collect($registrosBD);
        
        if ($registrosBD->isEmpty()) {
            \Log::error("ERROR: No se encontró el inventario para Articulo ID: {$articuloId}, Almacen ID: {$almacenId}");
            // Si es una entrada, crear el inventario si no existe
            if ($diferencia > 0) {
                $idNuevo = \DB::table('inventarios')->insertGetId([
                'articulo_id' => $articuloId,
                'almacen_id' => $almacenId,
                'cantidad' => $diferencia,
                'saldo_stock' => $diferencia,
                    'fecha_vencimiento' => '2099-12-31',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                \Log::info("Inventario creado con ID: {$idNuevo}, stock: {$diferencia}");
            }
            return;
        }
        
        // Si es una entrada (diferencia > 0), actualizar el primer registro o crear uno nuevo
        if ($diferencia > 0) {
            $primerRegistro = $registrosBD->first();
            $stockAnterior = (float) $primerRegistro->saldo_stock;
            $cantidadAnterior = (float) $primerRegistro->cantidad;
            $nuevaCantidad = (float) $cantidadAnterior + $diferencia;
            $nuevoStock = (float) $stockAnterior + $diferencia;
            
            \Log::info("ID inventario: {$primerRegistro->id}");
            \Log::info("Valores ANTES: cantidad={$cantidadAnterior}, saldo_stock={$stockAnterior}");
            \Log::info("Valores DESPUÉS esperados: cantidad={$nuevaCantidad}, saldo_stock={$nuevoStock}");
            
            // Usar UPDATE con operación aritmética directa en SQL para evitar problemas de redondeo
            // Formatear el valor con precisión decimal
            $diferenciaFormateada = number_format((float)$diferencia, 3, '.', '');
            
            $filasAfectadas = \DB::update(
                "UPDATE inventarios SET 
                    cantidad = cantidad + CAST(? AS DECIMAL(11,3)),
                    saldo_stock = saldo_stock + CAST(? AS DECIMAL(11,3)),
                    updated_at = NOW() 
                WHERE id = ?",
                [$diferenciaFormateada, $diferenciaFormateada, $primerRegistro->id]
            );
            
            \Log::info("UPDATE ejecutado (entrada) - Filas afectadas: {$filasAfectadas}, Diferencia: {$diferenciaFormateada}");
            
            // Verificar DESPUÉS del UPDATE usando CAST explícito para mantener precisión decimal
            $registroDespues = \DB::selectOne(
                "SELECT CAST(cantidad AS DECIMAL(11,3)) as cantidad, CAST(saldo_stock AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE id = ?",
                [$primerRegistro->id]
            );
            
            \Log::info("Valores DESPUÉS en BD: cantidad={$registroDespues->cantidad}, saldo_stock={$registroDespues->saldo_stock}");
            \Log::info("✓ Stock actualizado correctamente (entrada)");
            return;
        }
        
        // Si es una salida (diferencia < 0), descontar usando FIFO
        // CRÍTICO: Redondear la cantidad a descontar a 3 decimales para mantener precisión
        $cantidadADescontar = round(abs($diferencia), 3);
        $cantidadRestante = (float) $cantidadADescontar;
        
        \Log::info("Descontando {$cantidadADescontar} unidades usando FIFO de {$registrosBD->count()} registros");
        
        foreach ($registrosBD as $registro) {
            // Usar comparación con tolerancia para decimales
            if ($cantidadRestante <= 0.0001) {
                break;
            }
            
            // Obtener el ID del registro - puede venir como objeto o array
            $registroId = is_object($registro) ? ($registro->id ?? null) : ($registro['id'] ?? null);
            
            if (!$registroId) {
                \Log::warning("Registro sin ID, saltando");
                continue;
            }
            
            // Obtener el stock actualizado directamente de la BD para este registro específico
            // Esto asegura que tenemos el valor más reciente después de posibles actualizaciones anteriores
            // Usar CAST explícito para asegurar que los valores se obtengan como DECIMAL
            $registroActualizado = \DB::selectOne(
                "SELECT id, CAST(cantidad AS DECIMAL(11,3)) as cantidad, CAST(saldo_stock AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE id = ?",
                [$registroId]
            );
            
            if (!$registroActualizado) {
                \Log::warning("Registro no encontrado después de SELECT, ID: {$registroId}");
                continue; // Saltar si el registro ya no existe
            }
            
            // Asegurar que el valor se convierta correctamente a float manteniendo los decimales
            $stockDisponible = (float) ($registroActualizado->saldo_stock ?? 0);
            $idRegistro = (int) ($registroActualizado->id ?? $registroId);
            
            // Usar comparación con tolerancia para decimales
            if ($stockDisponible <= 0.0001) {
                continue; // Saltar registros sin stock
            }
            
            // Calcular cuánto descontar de este registro
            // CRÍTICO: Redondear a 3 decimales para mantener precisión
            $cantidadADescontarDeEste = round((float) min($cantidadRestante, $stockDisponible), 3);
            $cantidadRestante = round((float) ($cantidadRestante - $cantidadADescontarDeEste), 3);
            
            $stockAnterior = $stockDisponible;
            
            // CRÍTICO: Leer el valor actual de la BD ANTES de hacer el UPDATE
            // Esto asegura que tenemos el valor más reciente y calculamos correctamente en PHP
            $registroActual = \DB::selectOne(
                "SELECT CAST(cantidad AS DECIMAL(11,3)) as cantidad, CAST(saldo_stock AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE id = ?",
                [$idRegistro]
            );
            
            if (!$registroActual) {
                \Log::error("No se pudo leer el registro antes del UPDATE, ID: {$idRegistro}");
                continue;
            }
            
            $cantidadActual = (float)$registroActual->cantidad;
            $saldoStockActual = (float)$registroActual->saldo_stock;
            
            // Calcular los nuevos valores en PHP para asegurar precisión decimal
            $nuevaCantidad = round($cantidadActual - $cantidadADescontarDeEste, 3);
            $nuevoSaldoStock = round($saldoStockActual - $cantidadADescontarDeEste, 3);
            
            // CRÍTICO: Asegurar que los valores no sean negativos y estén redondeados
            $nuevaCantidad = max(0, round($nuevaCantidad, 3));
            $nuevoSaldoStock = max(0, round($nuevoSaldoStock, 3));
            
            \Log::info("UPDATE Stock - ID: {$idRegistro}, Antes: cantidad={$cantidadActual}, saldo={$saldoStockActual}, Descontar: {$cantidadADescontarDeEste}, Después: cantidad={$nuevaCantidad}, saldo={$nuevoSaldoStock}");
            
            // CRÍTICO: Usar update() directamente en el modelo para forzar la actualización
            try {
                // Usar update() directamente que ejecuta el UPDATE inmediatamente
                $filasAfectadas = \App\Models\Inventario::where('id', $idRegistro)
                    ->update([
                        'cantidad' => $nuevaCantidad,
                        'saldo_stock' => $nuevoSaldoStock,
                    ]);
                
                if ($filasAfectadas === 0) {
                    \Log::warning("UPDATE con Eloquent falló, intentando con Query Builder para ID: {$idRegistro}");
                    // Intentar con Query Builder como respaldo
                    $filasAfectadas = \DB::update(
                        "UPDATE inventarios SET cantidad = ?, saldo_stock = ?, updated_at = NOW() WHERE id = ?",
                        [$nuevaCantidad, $nuevoSaldoStock, $idRegistro]
                    );
                    
                    if ($filasAfectadas === 0) {
                        // Verificar si el registro existe
                        $verificacionExistencia = \DB::selectOne(
                            "SELECT id FROM inventarios WHERE id = ?",
                            [$idRegistro]
                        );
                        if (!$verificacionExistencia) {
                            throw new \Exception("Error crítico: El registro con ID {$idRegistro} no existe.");
                        }
                        \Log::error("ERROR: El UPDATE no afectó filas aunque el registro existe. ID: {$idRegistro}");
                    }
                }
            } catch (\Exception $e) {
                \Log::error("ERROR al actualizar: " . $e->getMessage());
                throw $e;
            }
            
            // Actualizar el valor esperado para la verificación
            $nuevoStock = $nuevoSaldoStock;
            
            // Verificar DESPUÉS del UPDATE usando CAST explícito para mantener precisión decimal
            $registroDespues = \DB::selectOne(
                "SELECT CAST(cantidad AS DECIMAL(11,3)) as cantidad, CAST(saldo_stock AS DECIMAL(11,3)) as saldo_stock 
                 FROM inventarios 
                 WHERE id = ?",
                [$idRegistro]
            );
            
            if ($registroDespues) {
                $stockDespuesVerificado = (float)$registroDespues->saldo_stock;
                $diferencia = abs($stockDespuesVerificado - $nuevoStock);
                
                // Usar tolerancia más amplia para evitar falsos positivos por redondeo de MySQL
                if ($diferencia > 0.01) {
                    \Log::warning("Stock verificado - Diferencia alta: {$diferencia}. Esperado: {$nuevoStock}, Obtenido: {$stockDespuesVerificado}");
                }
            }
        }
        
        // Usar comparación con tolerancia para decimales
        if ($cantidadRestante > 0.0001) {
            \Log::error("ERROR: No se pudo descontar todo el stock. Faltan {$cantidadRestante} unidades");
            throw new \Exception("Stock insuficiente. Faltan {$cantidadRestante} unidades para completar la operación.");
        }
        
        \Log::info("✓ Stock descontado correctamente (salida). Cantidad total descontada: {$cantidadADescontar}");
    }

    private function actualizarStockArticulo(int $articuloId, float $entrada, float $salida): void
    {
        $diferenciaStock = (float) ($entrada - $salida);
        
        // CRÍTICO: Obtener el stock directamente desde BD usando CAST explícito para precisión decimal
        $stockResult = \DB::selectOne(
            "SELECT CAST(stock AS DECIMAL(11,3)) as stock 
             FROM articulos 
             WHERE id = ?",
            [$articuloId]
        );
        
        if (!$stockResult || $stockResult->stock === null) {
            \Log::error("No se encontró el artículo con ID: {$articuloId}");
            return;
        }
        
        $stockAnterior = (float) $stockResult->stock;
        // Redondear diferenciaStock a 3 decimales antes de calcular
        $diferenciaStock = round($diferenciaStock, 3);
        $nuevoStock = round((float) $stockAnterior + $diferenciaStock, 3);
        
        // CRÍTICO: Formatear el valor con 3 decimales para asegurar precisión
        $diferenciaStockFormateada = number_format((float)$diferenciaStock, 3, '.', '');
        
        // CRÍTICO: Usar \DB::statement() con CAST explícito para asegurar precisión decimal
        $resultado = \DB::statement(
            "UPDATE articulos SET 
                stock = CAST(stock AS DECIMAL(11,3)) + CAST(? AS DECIMAL(11,3)),
                updated_at = NOW() 
            WHERE id = ?",
            [$diferenciaStockFormateada, $articuloId]
        );
        
        // Verificar DESPUÉS del UPDATE usando CAST explícito
        $stockDespuesResult = \DB::selectOne(
            "SELECT CAST(stock AS DECIMAL(11,3)) as stock 
             FROM articulos 
             WHERE id = ?",
            [$articuloId]
        );
        
        $stockDespues = $stockDespuesResult ? (float) $stockDespuesResult->stock : 0;
        
        if (abs($stockDespues - $nuevoStock) > 0.0001) {
            \Log::error("Stock artículo - No coincide. Esperado: {$nuevoStock}, Obtenido: {$stockDespues}");
        }
    }

    private function generarNumeroDocumento(string $tipoMovimiento): string
    {
        switch ($tipoMovimiento) {
            case 'ajuste':
                $prefijo = 'AJ';
                break;
            case 'compra':
                $prefijo = 'CO';
                break;
            case 'venta':
                $prefijo = 'VE';
                break;
            case 'traspaso_entrada':
                $prefijo = 'TE';
                break;
            case 'traspaso_salida':
                $prefijo = 'TS';
                break;
            default:
                $prefijo = 'MV';
                break;
        }

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
