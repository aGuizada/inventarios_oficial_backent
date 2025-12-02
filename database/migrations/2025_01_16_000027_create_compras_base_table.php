<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras_base', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('proveedor_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->string('tipo_comprobante', 20);
            $table->string('serie_comprobante', 7)->nullable();
            $table->string('num_comprobante', 10);
            $table->dateTime('fecha_hora');
            $table->decimal('total', 11, 2);
            $table->string('estado', 20)->default('Activo');
            $table->integer('almacen_id')->unsigned()->nullable();
            $table->integer('caja_id')->unsigned();
            $table->decimal('descuento_global', 8, 2)->default(0.00);
            $table->enum('tipo_compra', ['CONTADO', 'CREDITO']);
            $table->timestamps();

            $table->foreign('almacen_id')->references('id')->on('almacenes')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_base');
    }
};
