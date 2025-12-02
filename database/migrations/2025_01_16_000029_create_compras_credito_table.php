<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras_credito', function (Blueprint $table) {
            $table->integer('id')->unsigned()->primary();
            $table->integer('num_cuotas');
            $table->integer('frecuencia_dias');
            $table->decimal('cuota_inicial', 11, 2)->default(0.00);
            $table->string('tipo_pago_cuota', 20)->nullable();
            $table->integer('dias_gracia')->default(0);
            $table->decimal('interes_moratorio', 5, 2)->default(0.00);
            $table->string('estado_credito', 20)->default('Pendiente');
            $table->timestamps();

            $table->foreign('id')->references('id')->on('compras_base')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_credito');
    }
};
