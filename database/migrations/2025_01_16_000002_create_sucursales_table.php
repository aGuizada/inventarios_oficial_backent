<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id')->unsigned();
            $table->string('nombre', 50)->nullable();
            $table->integer('codigoSucursal')->unsigned();
            $table->string('direccion', 100);
            $table->string('correo', 191);
            $table->string('telefono', 191);
            $table->string('departamento', 191)->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
            $table->string('responsable', 50)->nullable();

            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
