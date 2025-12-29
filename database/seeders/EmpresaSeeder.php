<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('empresas')->insert([
            'nombre' => 'AUTOZONE ',
            'direccion' => '',
            'telefono' => '12345678',
            'email' => 'autozone@gmail.com',
            'nit' => '1234567890',
            'logo' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
