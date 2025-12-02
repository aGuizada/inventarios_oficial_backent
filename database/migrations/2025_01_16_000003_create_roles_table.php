<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 30);
            $table->string('descripcion', 100)->nullable();
            $table->boolean('estado')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
