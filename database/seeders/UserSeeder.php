<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolAdminId = DB::table('roles')->where('nombre', 'Administrador')->value('id');
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');
        if (! $rolAdminId || ! $sucursalId) {
            throw new \RuntimeException('Ejecute RolSeeder, EmpresaSeeder y SucursalSeeder antes de UserSeeder.');
        }

        DB::table('users')->insert([
            [
                'name' => 'Administrador',
                'email' => 'admin@miempresa.com',
                'password' => Hash::make('admin123'),
                'telefono' => '12345678',
                'usuario' => 'admin',
                'rol_id' => $rolAdminId,
                'sucursal_id' => $sucursalId,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
