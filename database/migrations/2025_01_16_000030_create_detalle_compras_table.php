<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('compra_base_id')->unsigned();
            $table->integer('articulo_id')->unsigned();
            $table->integer('cantidad');
            $table->decimal('descuento', 11, 2)->default(0.00);
            $table->decimal('precio', 11, 2);
            $table->timestamps();

            $table->foreign('articulo_id')->references('id')->on('articulos')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('compra_base_id')->references('id')->on('compras_base')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
    }
};
