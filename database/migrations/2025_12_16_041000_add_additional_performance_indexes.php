<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration para agregar índices adicionales de rendimiento
 * Verifica si cada índice existe antes de crearlo para evitar errores
 */
return new class extends Migration {
    /**
     * Helper para verificar si un índice existe
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    /**
     * Helper para crear índice solo si no existe
     */
    private function addIndexIfNotExists(string $table, string $indexName, array|string $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            $columnsStr = is_array($columns) ? implode(', ', $columns) : $columns;
            DB::statement("ALTER TABLE {$table} ADD INDEX {$indexName} ({$columnsStr})");
        }
    }

    /**
     * Helper para eliminar índice solo si existe
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // === VENTAS ===
        $this->addIndexIfNotExists('ventas', 'idx_ventas_estado', 'estado');
        $this->addIndexIfNotExists('ventas', 'idx_ventas_cliente_fecha', 'cliente_id, fecha_hora');
        $this->addIndexIfNotExists('ventas', 'idx_ventas_user_fecha', 'user_id, fecha_hora');
        $this->addIndexIfNotExists('ventas', 'idx_ventas_caja_fecha', 'caja_id, fecha_hora');
        $this->addIndexIfNotExists('ventas', 'idx_ventas_tipo_venta', 'tipo_venta_id');

        // === ARTÍCULOS ===
        $this->addIndexIfNotExists('articulos', 'idx_articulos_codigo', 'codigo');
        $this->addIndexIfNotExists('articulos', 'idx_articulos_nombre', 'nombre');
        $this->addIndexIfNotExists('articulos', 'idx_articulos_estado', 'estado');
        $this->addIndexIfNotExists('articulos', 'idx_articulos_categoria', 'categoria_id');
        $this->addIndexIfNotExists('articulos', 'idx_articulos_proveedor', 'proveedor_id');

        // === INVENTARIOS ===
        $this->addIndexIfNotExists('inventarios', 'idx_inventarios_articulo_almacen', 'articulo_id, almacen_id');
        $this->addIndexIfNotExists('inventarios', 'idx_inventarios_almacen', 'almacen_id');

        // === DETALLE_VENTAS ===
        $this->addIndexIfNotExists('detalle_ventas', 'idx_detalle_ventas_articulo_venta', 'articulo_id, venta_id');

        // === DETALLE_COMPRAS ===
        $this->addIndexIfNotExists('detalle_compras', 'idx_detalle_compras_articulo', 'articulo_id');
        $this->addIndexIfNotExists('detalle_compras', 'idx_detalle_compras_articulo_compra', 'articulo_id, compra_base_id');

        // === CLIENTES ===
        $this->addIndexIfNotExists('clientes', 'idx_clientes_nombre', 'nombre');
        $this->addIndexIfNotExists('clientes', 'idx_clientes_num_documento', 'num_documento');

        // === COMPRAS_BASE ===
        $this->addIndexIfNotExists('compras_base', 'idx_compras_base_proveedor_fecha', 'proveedor_id, fecha_hora');
        $this->addIndexIfNotExists('compras_base', 'idx_compras_base_caja', 'caja_id');

        // === CAJAS ===
        $this->addIndexIfNotExists('cajas', 'idx_cajas_sucursal', 'sucursal_id');
        $this->addIndexIfNotExists('cajas', 'idx_cajas_estado', 'estado');

        // === CREDITO_VENTAS ===
        if (Schema::hasTable('credito_ventas')) {
            $this->addIndexIfNotExists('credito_ventas', 'idx_credito_ventas_estado', 'estado');
            $this->addIndexIfNotExists('credito_ventas', 'idx_credito_ventas_venta', 'venta_id');
        }

        // === CUOTAS_CREDITO ===
        if (Schema::hasTable('cuotas_credito')) {
            $this->addIndexIfNotExists('cuotas_credito', 'idx_cuotas_credito_estado', 'estado');
            $this->addIndexIfNotExists('cuotas_credito', 'idx_cuotas_credito_fecha_pago', 'fecha_pago');
            $this->addIndexIfNotExists('cuotas_credito', 'idx_cuotas_credito_venta_estado', 'credito_id, estado');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar índices en orden inverso (solo si existen)
        if (Schema::hasTable('cuotas_credito')) {
            $this->dropIndexIfExists('cuotas_credito', 'idx_cuotas_credito_estado');
            $this->dropIndexIfExists('cuotas_credito', 'idx_cuotas_credito_fecha_pago');
            $this->dropIndexIfExists('cuotas_credito', 'idx_cuotas_credito_venta_estado');
        }

        if (Schema::hasTable('credito_ventas')) {
            $this->dropIndexIfExists('credito_ventas', 'idx_credito_ventas_estado');
            $this->dropIndexIfExists('credito_ventas', 'idx_credito_ventas_venta');
        }

        $this->dropIndexIfExists('cajas', 'idx_cajas_sucursal');
        $this->dropIndexIfExists('cajas', 'idx_cajas_estado');

        $this->dropIndexIfExists('compras_base', 'idx_compras_base_proveedor_fecha');
        $this->dropIndexIfExists('compras_base', 'idx_compras_base_caja');

        $this->dropIndexIfExists('clientes', 'idx_clientes_nombre');
        $this->dropIndexIfExists('clientes', 'idx_clientes_num_documento');

        $this->dropIndexIfExists('detalle_compras', 'idx_detalle_compras_articulo');
        $this->dropIndexIfExists('detalle_compras', 'idx_detalle_compras_articulo_compra');

        $this->dropIndexIfExists('detalle_ventas', 'idx_detalle_ventas_articulo_venta');

        $this->dropIndexIfExists('inventarios', 'idx_inventarios_articulo_almacen');
        $this->dropIndexIfExists('inventarios', 'idx_inventarios_almacen');

        $this->dropIndexIfExists('articulos', 'idx_articulos_codigo');
        $this->dropIndexIfExists('articulos', 'idx_articulos_nombre');
        $this->dropIndexIfExists('articulos', 'idx_articulos_estado');
        $this->dropIndexIfExists('articulos', 'idx_articulos_categoria');
        $this->dropIndexIfExists('articulos', 'idx_articulos_proveedor');

        $this->dropIndexIfExists('ventas', 'idx_ventas_estado');
        $this->dropIndexIfExists('ventas', 'idx_ventas_cliente_fecha');
        $this->dropIndexIfExists('ventas', 'idx_ventas_user_fecha');
        $this->dropIndexIfExists('ventas', 'idx_ventas_caja_fecha');
        $this->dropIndexIfExists('ventas', 'idx_ventas_tipo_venta');
    }
};
