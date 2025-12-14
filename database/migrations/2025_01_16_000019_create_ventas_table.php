<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('cliente_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->integer('tipo_venta_id')->unsigned();
            $table->integer('tipo_pago_id')->unsigned()->nullable();
            $table->string('tipo_comprobante', 20);
            $table->string('serie_comprobante', 7)->nullable();
            $table->string('num_comprobante', 10);
            $table->dateTime('fecha_hora');
            $table->decimal('total', 11, 2);
            $table->string('estado', 20)->default('Activo');
            $table->integer('caja_id')->unsigned();
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('tipo_pago_id')->references('id')->on('tipo_pagos')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('tipo_venta_id')->references('id')->on('tipo_ventas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
