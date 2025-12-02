<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras_cuotas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('compra_credito_id')->unsigned();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('monto_cuota', 11, 2);
            $table->decimal('monto_pagado', 11, 2)->default(0.00);
            $table->decimal('saldo_pendiente', 11, 2);
            $table->date('fecha_pago')->nullable();
            $table->string('estado', 20)->default('Pendiente');
            $table->timestamps();

            $table->foreign('compra_credito_id')->references('id')->on('compras_credito')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_cuotas');
    }
};
