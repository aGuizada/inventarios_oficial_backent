<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('almacen_id')->unsigned();
            $table->integer('articulo_id')->unsigned();
            $table->date('fecha_vencimiento')->default('2099-01-01');
            $table->decimal('saldo_stock', 11, 3)->default(0);
            $table->decimal('cantidad', 11, 3)->default(0);
            $table->timestamps();

            $table->foreign('almacen_id')->references('id')->on('almacenes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('articulo_id')->references('id')->on('articulos')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
