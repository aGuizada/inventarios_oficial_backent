<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cotizacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cliente_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->integer('almacen_id')->unsigned();
            $table->dateTime('fecha_hora')->useCurrent();
            $table->decimal('total', 11, 2);
            $table->dateTime('validez')->nullable();
            $table->string('plazo_entrega', 20)->nullable();
            $table->string('tiempo_entrega', 50);
            $table->string('lugar_entrega', 20)->nullable();
            $table->string('forma_pago', 20)->nullable();
            $table->string('nota', 50)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();

            $table->foreign('almacen_id')->references('id')->on('almacenes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion');
    }
};
