<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre_almacen', 100);
            $table->string('ubicacion', 191)->nullable();
            $table->integer('sucursal_id')->unsigned();
            $table->string('telefono', 191)->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();

            $table->foreign('sucursal_id')->references('id')->on('sucursales')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenes');
    }
};
