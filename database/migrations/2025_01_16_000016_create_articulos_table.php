<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articulos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('categoria_id')->unsigned();
            $table->integer('proveedor_id')->unsigned();
            $table->integer('medida_id')->unsigned();
            $table->integer('marca_id')->unsigned();
            $table->integer('industria_id')->unsigned();
            $table->string('codigo', 255)->nullable();
            $table->string('nombre', 255);
            $table->integer('unidad_envase');
            $table->decimal('precio_costo_unid', 15, 4);
            $table->decimal('precio_costo_paq', 15, 4);
            $table->decimal('precio_venta', 15, 4);
            $table->decimal('precio_uno', 15, 4)->nullable();
            $table->decimal('precio_dos', 15, 4)->nullable();
            $table->decimal('precio_tres', 15, 4)->nullable();
            $table->decimal('precio_cuatro', 15, 4)->nullable();
            $table->integer('stock');
            $table->string('descripcion', 256)->nullable();
            $table->boolean('estado')->default(1);
            $table->decimal('costo_compra', 10, 2);
            $table->integer('vencimiento')->nullable();
            $table->string('fotografia', 191)->nullable();
            $table->timestamps();

            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('medida_id')->references('id')->on('medidas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('marca_id')->references('id')->on('marcas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('industria_id')->references('id')->on('industrias')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
