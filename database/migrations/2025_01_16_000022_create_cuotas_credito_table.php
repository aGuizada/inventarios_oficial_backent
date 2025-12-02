<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuotas_credito', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('credito_id')->unsigned();
            $table->integer('cobrador_id')->unsigned()->nullable();
            $table->integer('numero_cuota');
            $table->dateTime('fecha_pago');
            $table->dateTime('fecha_cancelado')->nullable();
            $table->decimal('precio_cuota', 11, 2);
            $table->decimal('saldo_restante', 11, 2);
            $table->string('estado', 191)->default('Pendiente');
            $table->timestamps();

            $table->foreign('cobrador_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade'); // Changed to users
            $table->foreign('credito_id')->references('id')->on('credito_ventas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas_credito');
    }
};
