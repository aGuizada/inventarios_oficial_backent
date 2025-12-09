<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_trabajos', function (Blueprint $table) {
            $table->boolean('mostrar_costo_unitario')->default(true);
            $table->boolean('mostrar_costo_paquete')->default(true);
            $table->boolean('mostrar_costo_compra')->default(true);
            $table->boolean('mostrar_precios_adicionales')->default(true);
            $table->boolean('mostrar_vencimiento')->default(true);
            $table->boolean('mostrar_stock')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_trabajos', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_costo_unitario',
                'mostrar_costo_paquete',
                'mostrar_costo_compra',
                'mostrar_precios_adicionales',
                'mostrar_vencimiento',
                'mostrar_stock'
            ]);
        });
    }
};
