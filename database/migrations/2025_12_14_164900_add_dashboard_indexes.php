<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para agregar índices de optimización
 * para las consultas de dashboard con filtros de fecha
 * y cálculos de utilidad de artículos
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índice para filtros de fecha en ventas
        Schema::table('ventas', function (Blueprint $table) {
            // Índice compuesto para fecha_hora (usado en todos los filtros)
            $table->index('fecha_hora', 'idx_ventas_fecha_hora');
        });

        // Índices para optimizar joins en cálculo de utilidad
        Schema::table('detalle_ventas', function (Blueprint $table) {
            // Índice para join con articulos
            if (!Schema::hasColumn('detalle_ventas', 'articulo_id')) {
                // El índice ya existe como foreign key, pero lo añadimos explícitamente
                $table->index('articulo_id', 'idx_detalle_ventas_articulo');
            }

            // Índice para join con ventas
            if (!Schema::hasColumn('detalle_ventas', 'venta_id')) {
                $table->index('venta_id', 'idx_detalle_ventas_venta');
            }
        });

        // Índice para compras (usado en gráficas comparativas)
        Schema::table('compras_base', function (Blueprint $table) {
            $table->index('fecha_hora', 'idx_compras_fecha_hora');
        });

        // Índices para inventario (usado en KPIs)
        Schema::table('inventarios', function (Blueprint $table) {
            // Índice para filtros de stock bajo
            $table->index('saldo_stock', 'idx_inventarios_saldo');

            // Índice para join con articulos
            if (!Schema::hasColumn('inventarios', 'articulo_id')) {
                $table->index('articulo_id', 'idx_inventarios_articulo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_fecha_hora');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropIndex('idx_detalle_ventas_articulo');
            $table->dropIndex('idx_detalle_ventas_venta');
        });

        Schema::table('compras_base', function (Blueprint $table) {
            $table->dropIndex('idx_compras_fecha_hora');
        });

        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropIndex('idx_inventarios_saldo');
            $table->dropIndex('idx_inventarios_articulo');
        });
    }
};
