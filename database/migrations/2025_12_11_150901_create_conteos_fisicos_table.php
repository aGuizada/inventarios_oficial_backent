<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conteos_fisicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('almacen_id');
            $table->date('fecha_conteo');
            $table->string('responsable', 100);
            $table->enum('estado', ['en_proceso', 'finalizado', 'cancelado'])->default('en_proceso');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->index('almacen_id');
            $table->index('fecha_conteo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteos_fisicos');
    }
};
