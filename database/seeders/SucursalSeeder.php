<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sucursales')->insert([
            'empresa_id' => 1, // Asume que la empresa con ID 1 ya existe
            'nombre' => 'AUTOZONE',
            'codigoSucursal' => 1,
            'direccion' => 'Av. Principal #123',
            'correo' => 'autozone@autozone@gmain.com',
            'telefono' => '12345678',
            'departamento' => 'cochabamba',
            'estado' => 1,
            'responsable' => 'Administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
