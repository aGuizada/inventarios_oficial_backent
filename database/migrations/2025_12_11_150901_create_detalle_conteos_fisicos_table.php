<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_conteos_fisicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conteo_fisico_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad_sistema', 10, 2);
            $table->decimal('cantidad_contada', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->default(0);
            $table->decimal('costo_unitario', 10, 2);
            $table->timestamps();

            $table->index('conteo_fisico_id');
            $table->index('articulo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_conteos_fisicos');
    }
};
