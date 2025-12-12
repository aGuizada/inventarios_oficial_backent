<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devoluciones_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->date('fecha');
            $table->string('motivo', 100);
            $table->decimal('monto_devuelto', 10, 2);
            $table->enum('estado', ['pendiente', 'procesada', 'rechazada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->index('venta_id');
            $table->index('fecha');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devoluciones_ventas');
    }
};
