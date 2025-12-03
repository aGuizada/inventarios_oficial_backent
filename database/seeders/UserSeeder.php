<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Administrador',
            'email' => 'admin@miempresa.com',
            'password' => Hash::make('admin123'),
            'telefono' => '12345678',
            'usuario' => 'admin',
            'rol_id' => 1, // Administrador
            'sucursal_id' => 1, // Casa Matriz
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
