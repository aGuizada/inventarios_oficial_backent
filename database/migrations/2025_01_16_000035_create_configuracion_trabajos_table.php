<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configuracion_trabajos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('gestion', 5);
            $table->string('codigo_productos', 100);
            $table->integer('almacen_predeterminado')->nullable();
            $table->decimal('maximo_descuento', 6, 2)->nullable();
            $table->string('valuacion_inventario', 100);
            $table->boolean('backup_automatico')->default(0);
            $table->string('ruta_backup', 100)->nullable();
            $table->boolean('saldos_negativos')->default(0);
            $table->string('separador_decimales', 15);
            $table->boolean('mostrar_costos')->default(1);
            $table->boolean('mostrar_proveedores')->default(1);
            $table->boolean('mostrar_saldos_stock')->default(1);
            $table->boolean('actualizar_iva')->default(1);
            $table->boolean('permitir_devolucion')->default(0);
            $table->boolean('editar_nro_doc')->default(0);
            $table->boolean('registro_cliente_obligatorio')->default(1);
            $table->boolean('buscar_cliente_por_codigo')->default(1);
            $table->integer('moneda_principal_id')->unsigned();
            $table->integer('moneda_venta_id')->unsigned();
            $table->integer('moneda_compra_id')->unsigned();
            $table->integer('tiempo_min_caducidad_articulo')->nullable();
            $table->timestamps();

            $table->foreign('moneda_compra_id')->references('id')->on('monedas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('moneda_principal_id')->references('id')->on('monedas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('moneda_venta_id')->references('id')->on('monedas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_trabajos');
    }
};
