<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras_contado', function (Blueprint $table) {
            $table->integer('id')->unsigned()->primary();
            $table->dateTime('fecha_pago');
            $table->string('metodo_pago', 20);
            $table->string('referencia_pago', 50)->nullable();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('compras_base')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_contado');
    }
};
