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
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('tipo_movimiento', 50); // compra, venta, ajuste, traspaso_entrada, traspaso_salida
            $table->string('documento_tipo', 50)->nullable(); // factura, nota, guía
            $table->string('documento_numero', 100)->nullable();

            // Referencias a otras tablas - usando unsignedBigInteger para compatibilidad
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('almacen_id');

            // Cantidades (físicas)
            $table->decimal('cantidad_entrada', 10, 2)->default(0);
            $table->decimal('cantidad_salida', 10, 2)->default(0);
            $table->decimal('cantidad_saldo', 10, 2);

            // Valores monetarios
            $table->decimal('costo_unitario', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('precio_unitario', 10, 2)->nullable(); // Solo para ventas
            $table->decimal('precio_total', 10, 2)->nullable(); // Solo para ventas

            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('usuario_id');

            // Referencias opcionales a documentos originales
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('traspaso_id')->nullable();

            $table->timestamps();

            // Índices para consultas rápidas
            $table->index(['articulo_id', 'almacen_id', 'fecha']);
            $table->index('tipo_movimiento');
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
