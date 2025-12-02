<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('arqueo_cajas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('caja_id')->unsigned();
            $table->integer('user_id')->unsigned(); // Changed from usuario_id
            $table->integer('billete200')->default(0);
            $table->integer('billete100')->default(0);
            $table->integer('billete50')->default(0);
            $table->integer('billete20')->default(0);
            $table->integer('billete10')->default(0);
            $table->integer('moneda5')->default(0);
            $table->integer('moneda2')->default(0);
            $table->integer('moneda1')->default(0);
            $table->integer('moneda050')->default(0);
            $table->integer('moneda020')->default(0);
            $table->integer('moneda010')->default(0);
            $table->decimal('total_efectivo', 11, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade'); // Changed to users
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueo_cajas');
    }
};
