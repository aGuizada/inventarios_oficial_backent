<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration COMPLETA para agregar TODOS los índices restantes
 * para máximo rendimiento en todas las tablas de la base de datos
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
    private function addIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE {$table} ADD INDEX {$indexName} ({$columns})");
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
        // =====================================================
        // TRASPASOS - Muy importante para gestión de inventario
        // =====================================================
        if (Schema::hasTable('traspasos')) {
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_estado', 'estado');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_fecha_solicitud', 'fecha_solicitud');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_tipo', 'tipo_traspaso');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_sucursal_origen', 'sucursal_origen_id');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_sucursal_destino', 'sucursal_destino_id');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_almacen_origen', 'almacen_origen_id');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_almacen_destino', 'almacen_destino_id');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_user', 'user_id');
            $this->addIndexIfNotExists('traspasos', 'idx_traspasos_estado_fecha', 'estado, fecha_solicitud');
        }

        // =====================================================
        // DETALLE_TRASPASOS
        // =====================================================
        if (Schema::hasTable('detalle_traspasos')) {
            $this->addIndexIfNotExists('detalle_traspasos', 'idx_det_traspasos_traspaso', 'traspaso_id');
            $this->addIndexIfNotExists('detalle_traspasos', 'idx_det_traspasos_articulo', 'articulo_id');
            $this->addIndexIfNotExists('detalle_traspasos', 'idx_det_traspasos_estado', 'estado');
            $this->addIndexIfNotExists('detalle_traspasos', 'idx_det_traspasos_inventario', 'inventario_origen_id');
        }

        // =====================================================
        // HISTORIAL_TRASPASOS
        // =====================================================
        if (Schema::hasTable('historial_traspasos')) {
            $this->addIndexIfNotExists('historial_traspasos', 'idx_hist_traspasos_traspaso', 'traspaso_id');
            $this->addIndexIfNotExists('historial_traspasos', 'idx_hist_traspasos_fecha', 'created_at');
        }

        // =====================================================
        // COTIZACION & DETALLE
        // =====================================================
        if (Schema::hasTable('cotizacion')) {
            $this->addIndexIfNotExists('cotizacion', 'idx_cotizacion_cliente', 'cliente_id');
            $this->addIndexIfNotExists('cotizacion', 'idx_cotizacion_user', 'user_id');
            $this->addIndexIfNotExists('cotizacion', 'idx_cotizacion_fecha', 'fecha_hora');
            $this->addIndexIfNotExists('cotizacion', 'idx_cotizacion_estado', 'estado');
            $this->addIndexIfNotExists('cotizacion', 'idx_cotizacion_almacen', 'almacen_id');
        }

        if (Schema::hasTable('detalle_cotizacion')) {
            $this->addIndexIfNotExists('detalle_cotizacion', 'idx_det_cotizacion_cotizacion', 'cotizacion_id');
            $this->addIndexIfNotExists('detalle_cotizacion', 'idx_det_cotizacion_articulo', 'articulo_id');
        }

        // =====================================================
        // ARQUEO_CAJAS
        // =====================================================
        if (Schema::hasTable('arqueo_cajas')) {
            $this->addIndexIfNotExists('arqueo_cajas', 'idx_arqueo_caja', 'caja_id');
            $this->addIndexIfNotExists('arqueo_cajas', 'idx_arqueo_user', 'user_id');
            $this->addIndexIfNotExists('arqueo_cajas', 'idx_arqueo_fecha', 'created_at');
        }

        // =====================================================
        // TRANSACCIONES_CAJAS
        // =====================================================
        if (Schema::hasTable('transacciones_cajas')) {
            $this->addIndexIfNotExists('transacciones_cajas', 'idx_trans_caja_caja', 'caja_id');
            $this->addIndexIfNotExists('transacciones_cajas', 'idx_trans_caja_user', 'user_id');
            $this->addIndexIfNotExists('transacciones_cajas', 'idx_trans_caja_fecha', 'fecha');
            $this->addIndexIfNotExists('transacciones_cajas', 'idx_trans_caja_tipo', 'transaccion');
        }

        // =====================================================
        // DEVOLUCIONES_VENTAS & DETALLE
        // Ya tienen índices en la migración original (2025_12_11_135418)
        // user_id en esa tabla es 'usuario_id'
        // =====================================================
        if (Schema::hasTable('devoluciones_ventas')) {
            $this->addIndexIfNotExists('devoluciones_ventas', 'idx_devolucion_usuario', 'usuario_id');
        }

        if (Schema::hasTable('detalle_devoluciones_ventas')) {
            $this->addIndexIfNotExists('detalle_devoluciones_ventas', 'idx_det_devolucion_devolucion', 'devolucion_venta_id');
            $this->addIndexIfNotExists('detalle_devoluciones_ventas', 'idx_det_devolucion_articulo', 'articulo_id');
        }

        // =====================================================
        // CONTEOS_FISICOS & DETALLE
        // Ya tienen índices en la migración original (2025_12_11_150901)
        // Solo agregamos el índice de usuario (que usa 'usuario_id')
        // =====================================================
        if (Schema::hasTable('conteos_fisicos')) {
            $this->addIndexIfNotExists('conteos_fisicos', 'idx_conteo_usuario', 'usuario_id');
        }

        if (Schema::hasTable('detalle_conteos_fisicos')) {
            $this->addIndexIfNotExists('detalle_conteos_fisicos', 'idx_det_conteo_conteo', 'conteo_fisico_id');
            $this->addIndexIfNotExists('detalle_conteos_fisicos', 'idx_det_conteo_articulo', 'articulo_id');
        }

        // =====================================================
        // COMPRAS_CONTADO & COMPRAS_CREDITO
        // Usan patrón STI donde `id` es la FK a compras_base
        // No necesitan índices adicionales ya que id es PK
        // =====================================================
        if (Schema::hasTable('compras_credito')) {
            $this->addIndexIfNotExists('compras_credito', 'idx_compra_cred_estado', 'estado_credito');
        }

        // =====================================================
        // COMPRAS_CUOTAS
        // =====================================================
        if (Schema::hasTable('compras_cuotas')) {
            $this->addIndexIfNotExists('compras_cuotas', 'idx_compra_cuota_credito', 'compra_credito_id');
            $this->addIndexIfNotExists('compras_cuotas', 'idx_compra_cuota_estado', 'estado');
            $this->addIndexIfNotExists('compras_cuotas', 'idx_compra_cuota_fecha', 'fecha_vencimiento');
        }

        // =====================================================
        // USERS - muy importante para autenticación
        // =====================================================
        if (Schema::hasTable('users')) {
            $this->addIndexIfNotExists('users', 'idx_users_rol', 'rol_id');
            $this->addIndexIfNotExists('users', 'idx_users_sucursal', 'sucursal_id');
            $this->addIndexIfNotExists('users', 'idx_users_estado', 'estado');
        }

        // =====================================================
        // PROVEEDORES
        // =====================================================
        if (Schema::hasTable('proveedores')) {
            $this->addIndexIfNotExists('proveedores', 'idx_proveedores_nombre', 'nombre');
            $this->addIndexIfNotExists('proveedores', 'idx_proveedores_nit', 'num_documento');
        }

        // =====================================================
        // ALMACENES
        // =====================================================
        if (Schema::hasTable('almacenes')) {
            $this->addIndexIfNotExists('almacenes', 'idx_almacenes_sucursal', 'sucursal_id');
            $this->addIndexIfNotExists('almacenes', 'idx_almacenes_estado', 'estado');
        }

        // =====================================================
        // SUCURSALES
        // =====================================================
        if (Schema::hasTable('sucursales')) {
            $this->addIndexIfNotExists('sucursales', 'idx_sucursales_empresa', 'empresa_id');
            $this->addIndexIfNotExists('sucursales', 'idx_sucursales_estado', 'estado');
        }

        // =====================================================
        // DETALLE_PAGOS (pagos mixtos)
        // =====================================================
        if (Schema::hasTable('detalle_pagos')) {
            $this->addIndexIfNotExists('detalle_pagos', 'idx_det_pagos_venta', 'venta_id');
            $this->addIndexIfNotExists('detalle_pagos', 'idx_det_pagos_tipo', 'tipo_pago_id');
        }

        // =====================================================
        // NOTIFICATIONS (Laravel)
        // =====================================================
        if (Schema::hasTable('notifications')) {
            $this->addIndexIfNotExists('notifications', 'idx_notif_notifiable', 'notifiable_id, notifiable_type');
            $this->addIndexIfNotExists('notifications', 'idx_notif_read', 'read_at');
            $this->addIndexIfNotExists('notifications', 'idx_notif_created', 'created_at');
        }

        // =====================================================
        // CAJAS - índices adicionales
        // =====================================================
        if (Schema::hasTable('cajas')) {
            $this->addIndexIfNotExists('cajas', 'idx_cajas_user', 'user_id');
            $this->addIndexIfNotExists('cajas', 'idx_cajas_fecha_apertura', 'fecha_apertura');
        }

        // =====================================================
        // CREDITO_VENTAS - índices adicionales
        // =====================================================
        if (Schema::hasTable('credito_ventas')) {
            $this->addIndexIfNotExists('credito_ventas', 'idx_credito_ventas_cliente', 'cliente_id');
            $this->addIndexIfNotExists('credito_ventas', 'idx_credito_ventas_prox_pago', 'proximo_pago');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // TRASPASOS
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_estado');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_fecha_solicitud');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_tipo');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_sucursal_origen');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_sucursal_destino');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_almacen_origen');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_almacen_destino');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_user');
        $this->dropIndexIfExists('traspasos', 'idx_traspasos_estado_fecha');

        // DETALLE_TRASPASOS
        $this->dropIndexIfExists('detalle_traspasos', 'idx_det_traspasos_traspaso');
        $this->dropIndexIfExists('detalle_traspasos', 'idx_det_traspasos_articulo');
        $this->dropIndexIfExists('detalle_traspasos', 'idx_det_traspasos_estado');
        $this->dropIndexIfExists('detalle_traspasos', 'idx_det_traspasos_inventario');

        // HISTORIAL_TRASPASOS
        $this->dropIndexIfExists('historial_traspasos', 'idx_hist_traspasos_traspaso');
        $this->dropIndexIfExists('historial_traspasos', 'idx_hist_traspasos_fecha');

        // COTIZACION
        $this->dropIndexIfExists('cotizacion', 'idx_cotizacion_cliente');
        $this->dropIndexIfExists('cotizacion', 'idx_cotizacion_user');
        $this->dropIndexIfExists('cotizacion', 'idx_cotizacion_fecha');
        $this->dropIndexIfExists('cotizacion', 'idx_cotizacion_estado');
        $this->dropIndexIfExists('cotizacion', 'idx_cotizacion_almacen');

        // DETALLE_COTIZACION
        $this->dropIndexIfExists('detalle_cotizacion', 'idx_det_cotizacion_cotizacion');
        $this->dropIndexIfExists('detalle_cotizacion', 'idx_det_cotizacion_articulo');

        // ARQUEO_CAJAS
        $this->dropIndexIfExists('arqueo_cajas', 'idx_arqueo_caja');
        $this->dropIndexIfExists('arqueo_cajas', 'idx_arqueo_user');
        $this->dropIndexIfExists('arqueo_cajas', 'idx_arqueo_fecha');

        // TRANSACCIONES_CAJAS
        $this->dropIndexIfExists('transacciones_cajas', 'idx_trans_caja_caja');
        $this->dropIndexIfExists('transacciones_cajas', 'idx_trans_caja_user');
        $this->dropIndexIfExists('transacciones_cajas', 'idx_trans_caja_fecha');
        $this->dropIndexIfExists('transacciones_cajas', 'idx_trans_caja_tipo');

        // DEVOLUCIONES
        $this->dropIndexIfExists('devoluciones_ventas', 'idx_devolucion_usuario');
        $this->dropIndexIfExists('detalle_devoluciones_ventas', 'idx_det_devolucion_devolucion');
        $this->dropIndexIfExists('detalle_devoluciones_ventas', 'idx_det_devolucion_articulo');

        // CONTEOS_FISICOS
        $this->dropIndexIfExists('conteos_fisicos', 'idx_conteo_usuario');
        $this->dropIndexIfExists('detalle_conteos_fisicos', 'idx_det_conteo_conteo');
        $this->dropIndexIfExists('detalle_conteos_fisicos', 'idx_det_conteo_articulo');

        // COMPRAS
        $this->dropIndexIfExists('compras_credito', 'idx_compra_cred_estado');
        $this->dropIndexIfExists('compras_cuotas', 'idx_compra_cuota_credito');
        $this->dropIndexIfExists('compras_cuotas', 'idx_compra_cuota_estado');
        $this->dropIndexIfExists('compras_cuotas', 'idx_compra_cuota_fecha');

        // USERS
        $this->dropIndexIfExists('users', 'idx_users_rol');
        $this->dropIndexIfExists('users', 'idx_users_sucursal');
        $this->dropIndexIfExists('users', 'idx_users_estado');

        // PROVEEDORES
        $this->dropIndexIfExists('proveedores', 'idx_proveedores_nombre');
        $this->dropIndexIfExists('proveedores', 'idx_proveedores_nit');

        // ALMACENES
        $this->dropIndexIfExists('almacenes', 'idx_almacenes_sucursal');
        $this->dropIndexIfExists('almacenes', 'idx_almacenes_estado');

        // SUCURSALES
        $this->dropIndexIfExists('sucursales', 'idx_sucursales_empresa');
        $this->dropIndexIfExists('sucursales', 'idx_sucursales_estado');

        // DETALLE_PAGOS
        $this->dropIndexIfExists('detalle_pagos', 'idx_det_pagos_venta');
        $this->dropIndexIfExists('detalle_pagos', 'idx_det_pagos_tipo');

        // NOTIFICATIONS
        $this->dropIndexIfExists('notifications', 'idx_notif_notifiable');
        $this->dropIndexIfExists('notifications', 'idx_notif_read');
        $this->dropIndexIfExists('notifications', 'idx_notif_created');

        // CAJAS
        $this->dropIndexIfExists('cajas', 'idx_cajas_user');
        $this->dropIndexIfExists('cajas', 'idx_cajas_fecha_apertura');

        // CREDITO_VENTAS
        $this->dropIndexIfExists('credito_ventas', 'idx_credito_ventas_cliente');
        $this->dropIndexIfExists('credito_ventas', 'idx_credito_ventas_prox_pago');
    }
};
