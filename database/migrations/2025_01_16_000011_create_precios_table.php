<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('precios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre_precio', 100);
            $table->decimal('porcentaje', 11, 2);
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios');
    }
};
