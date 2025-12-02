<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historial_traspasos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('traspaso_id')->unsigned();
            $table->string('evento', 50);
            $table->text('descripcion');
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->dateTime('fecha_evento');
            $table->string('ip', 45)->nullable();
            $table->string('dispositivo', 100)->nullable();
            $table->timestamps();

            $table->foreign('traspaso_id')->references('id')->on('traspasos')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_traspasos');
    }
};
