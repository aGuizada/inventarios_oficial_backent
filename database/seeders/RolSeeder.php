<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'nombre' => 'Administrador',
                'descripcion' => 'Acceso total al sistema',
                'estado' => 1
            ],
            [
                'nombre' => 'Vendedor',
                'descripcion' => 'Acceso a ventas y consultas',
                'estado' => 1
            ]
        ]);
    }
}
