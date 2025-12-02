<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('traspasos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo_traspaso', 20)->unique();
            $table->integer('sucursal_origen_id')->unsigned();
            $table->integer('sucursal_destino_id')->unsigned();
            $table->integer('almacen_origen_id')->unsigned();
            $table->integer('almacen_destino_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->dateTime('fecha_solicitud');
            $table->dateTime('fecha_aprobacion')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->enum('tipo_traspaso', ['INTERNO', 'SUCURSAL', 'URGENTE'])->default('SUCURSAL');
            $table->enum('estado', ['PENDIENTE', 'APROBADO', 'EN_TRANSITO', 'RECIBIDO', 'RECHAZADO'])->default('PENDIENTE');
            $table->string('motivo', 200)->nullable();
            $table->text('observaciones')->nullable();
            $table->integer('usuario_aprobador_id')->unsigned()->nullable();
            $table->integer('usuario_receptor_id')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('almacen_destino_id')->references('id')->on('almacenes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('almacen_origen_id')->references('id')->on('almacenes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('sucursal_destino_id')->references('id')->on('sucursales')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('sucursal_origen_id')->references('id')->on('sucursales')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('usuario_aprobador_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade'); // Changed to users
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
            $table->foreign('usuario_receptor_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traspasos');
    }
};
