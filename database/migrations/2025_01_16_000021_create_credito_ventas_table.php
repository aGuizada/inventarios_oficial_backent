<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credito_ventas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('venta_id')->unsigned();
            $table->integer('cliente_id')->unsigned();
            $table->integer('numero_cuotas')->default(0);
            $table->integer('tiempo_dias_cuota')->default(0);
            $table->decimal('total', 11, 2)->nullable();
            $table->string('estado', 191)->default('Pendiente');
            $table->dateTime('proximo_pago')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credito_ventas');
    }
};
