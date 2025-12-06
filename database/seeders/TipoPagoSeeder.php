<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existen datos para evitar duplicados
        $existentes = DB::table('tipo_pagos')->count();
        
        if ($existentes === 0) {
            DB::table('tipo_pagos')->insert([
                [
                    'nombre_tipo_pago' => 'Efectivo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre_tipo_pago' => 'QR',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre_tipo_pago' => 'Transferencia',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre_tipo_pago' => 'Tarjeta de crédito',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre_tipo_pago' => 'Tarjeta de débito',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            
            $this->command->info('Tipos de pago creados exitosamente.');
        } else {
            $this->command->info('Ya existen tipos de pago en la base de datos.');
        }
    }
}





