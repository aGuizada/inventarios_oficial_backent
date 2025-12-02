<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones_cajas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('caja_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->dateTime('fecha');
            $table->string('transaccion', 50);
            $table->decimal('importe', 11, 2);
            $table->string('descripcion', 100)->nullable();
            $table->string('referencia', 50)->nullable();
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones_cajas');
    }
};
