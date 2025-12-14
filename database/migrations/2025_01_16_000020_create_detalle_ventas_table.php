<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('venta_id');
            $table->integer('articulo_id')->unsigned();
            $table->integer('cantidad');
            $table->decimal('precio', 11, 2);
            $table->decimal('descuento', 11, 2)->default(0.00);

            $table->foreign('articulo_id')->references('id')->on('articulos')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
