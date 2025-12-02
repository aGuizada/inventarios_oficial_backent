<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sucursal_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('saldo_inicial', 11, 2);
            $table->decimal('depositos', 11, 2)->default(0.00);
            $table->decimal('salidas', 11, 2)->default(0.00);
            $table->decimal('ventas', 11, 2)->default(0.00);
            $table->decimal('ventas_contado', 11, 2)->default(0.00);
            $table->decimal('ventas_credito', 11, 2)->default(0.00);
            $table->decimal('pagos_efectivo', 11, 2)->default(0.00);
            $table->decimal('pagos_qr', 11, 2)->default(0.00);
            $table->decimal('pagos_transferencia', 11, 2)->default(0.00);
            $table->decimal('cuotas_ventas_credito', 11, 2)->default(0.00);
            $table->decimal('compras_contado', 11, 2)->default(0.00);
            $table->decimal('compras_credito', 11, 2)->default(0.00);
            $table->decimal('saldo_faltante', 11, 2)->default(0.00);
            $table->decimal('saldo_caja', 11, 2)->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();

            $table->foreign('sucursal_id')->references('id')->on('sucursales')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
