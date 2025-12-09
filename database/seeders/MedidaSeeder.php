<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedidaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $medidas = [
            ['nombre_medida' => 'Unidad', 'estado' => 1],
            ['nombre_medida' => 'Paquete', 'estado' => 1],
            ['nombre_medida' => 'Centimetro', 'estado' => 1],
            ['nombre_medida' => 'Metro', 'estado' => 1],
            ['nombre_medida' => 'Litro', 'estado' => 1],
            ['nombre_medida' => 'Kilo', 'estado' => 1],
            ['nombre_medida' => 'Caja', 'estado' => 1],
        ];

        foreach ($medidas as $medida) {
            DB::table('medidas')->updateOrInsert(
                ['nombre_medida' => $medida['nombre_medida']],
                ['estado' => $medida['estado'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
