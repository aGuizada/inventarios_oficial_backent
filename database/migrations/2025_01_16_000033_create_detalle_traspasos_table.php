<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_traspasos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('traspaso_id')->unsigned();
            $table->integer('articulo_id')->unsigned();
            $table->integer('inventario_origen_id')->unsigned();
            $table->integer('cantidad_solicitada');
            $table->integer('cantidad_enviada')->default(0);
            $table->integer('cantidad_recibida')->default(0);
            $table->decimal('precio_costo', 15, 4)->nullable();
            $table->decimal('precio_venta', 15, 4)->nullable();
            $table->string('lote', 50)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('observaciones', 100)->nullable();
            $table->enum('estado', ['PENDIENTE', 'ENVIADO', 'RECIBIDO', 'DIFERENCIA', 'RECHAZADO'])->default('PENDIENTE');
            $table->timestamps();

            $table->foreign('articulo_id')->references('id')->on('articulos')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('inventario_origen_id')->references('id')->on('inventarios')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('traspaso_id')->references('id')->on('traspasos')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_traspasos');
    }
};
