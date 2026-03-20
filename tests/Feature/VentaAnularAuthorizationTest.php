<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentaAnularAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function idSucursalMatriz(): int
    {
        return (int) DB::table('sucursales')->orderBy('id')->value('id');
    }

    private function idRolVendedor(): int
    {
        return (int) DB::table('roles')->where('nombre', 'Vendedor')->value('id');
    }

    private function createVendedor(string $suffix): User
    {
        return User::factory()->create([
            'name' => 'Vendedor '.$suffix,
            'email' => 'v'.$suffix.'@test.com',
            'usuario' => 'vend'.$suffix,
            'rol_id' => $this->idRolVendedor(),
            'sucursal_id' => $this->idSucursalMatriz(),
            'estado' => 1,
        ]);
    }

    private function insertCliente(): int
    {
        return DB::table('clientes')->insertGetId([
            'nombre' => 'Cliente Test',
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAlmacen(): int
    {
        return DB::table('almacenes')->insertGetId([
            'nombre_almacen' => 'Almacén test',
            'sucursal_id' => $this->idSucursalMatriz(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCaja(int $userId): int
    {
        return DB::table('cajas')->insertGetId([
            'sucursal_id' => $this->idSucursalMatriz(),
            'user_id' => $userId,
            'fecha_apertura' => now(),
            'fecha_cierre' => null,
            'saldo_inicial' => 0,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertVentaActiva(int $ownerUserId, int $cajaId, int $clienteId, array $overrides = []): int
    {
        $row = array_merge([
            'cliente_id' => $clienteId,
            'user_id' => $ownerUserId,
            'tipo_venta_id' => (int) DB::table('tipo_ventas')->orderBy('id')->value('id'),
            'tipo_pago_id' => (int) DB::table('tipo_pagos')->orderBy('id')->value('id'),
            'tipo_comprobante' => 'ticket',
            'serie_comprobante' => null,
            'num_comprobante' => 'TEST001',
            'fecha_hora' => now(),
            'total' => 100.00,
            'estado' => 'Activo',
            'caja_id' => $cajaId,
            'almacen_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('ventas')->insertGetId($row);
    }

    public function test_vendedor_no_puede_anular_venta_de_otro_usuario(): void
    {
        $vendedorA = $this->createVendedor('a');
        $vendedorB = $this->createVendedor('b');
        $clienteId = $this->insertCliente();
        $cajaId = $this->insertCaja((int) $vendedorB->id);
        $ventaId = $this->insertVentaActiva((int) $vendedorB->id, $cajaId, $clienteId);

        Sanctum::actingAs($vendedorA);

        $response = $this->postJson("/api/ventas/{$ventaId}/anular");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No autorizado para realizar esta acción.',
        ]);
    }

    public function test_administrador_puede_anular_venta_sin_detalles(): void
    {
        $admin = User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
        $vendedor = $this->createVendedor('c');
        $clienteId = $this->insertCliente();
        $almacenId = $this->insertAlmacen();
        $cajaId = $this->insertCaja((int) $vendedor->id);
        $ventaId = $this->insertVentaActiva((int) $vendedor->id, $cajaId, $clienteId, [
            'almacen_id' => $almacenId,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/ventas/{$ventaId}/anular");

        $response->assertStatus(200);
        $this->assertSame('Anulado', DB::table('ventas')->where('id', $ventaId)->value('estado'));
    }

    public function test_vendedor_no_puede_ver_venta_de_otro_usuario(): void
    {
        $vendedorA = $this->createVendedor('ver-a');
        $vendedorB = $this->createVendedor('ver-b');
        $clienteId = $this->insertCliente();
        $cajaId = $this->insertCaja((int) $vendedorB->id);
        $ventaId = $this->insertVentaActiva((int) $vendedorB->id, $cajaId, $clienteId);

        Sanctum::actingAs($vendedorA);

        $response = $this->getJson("/api/ventas/{$ventaId}");
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No autorizado para realizar esta acción.',
        ]);
    }

    public function test_vendedor_no_puede_actualizar_venta_de_otro_usuario(): void
    {
        $vendedorA = $this->createVendedor('upd-a');
        $vendedorB = $this->createVendedor('upd-b');
        $clienteId = $this->insertCliente();
        $cajaId = $this->insertCaja((int) $vendedorB->id);
        $ventaId = $this->insertVentaActiva((int) $vendedorB->id, $cajaId, $clienteId);

        Sanctum::actingAs($vendedorA);

        $response = $this->putJson("/api/ventas/{$ventaId}", []);
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No autorizado para realizar esta acción.',
        ]);
    }

    public function test_vendedor_no_puede_eliminar_venta_de_otro_usuario(): void
    {
        $vendedorA = $this->createVendedor('del-a');
        $vendedorB = $this->createVendedor('del-b');
        $clienteId = $this->insertCliente();
        $cajaId = $this->insertCaja((int) $vendedorB->id);
        $ventaId = $this->insertVentaActiva((int) $vendedorB->id, $cajaId, $clienteId);

        Sanctum::actingAs($vendedorA);

        $response = $this->deleteJson("/api/ventas/{$ventaId}");
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No autorizado para realizar esta acción.',
        ]);
        $this->assertNotNull(DB::table('ventas')->where('id', $ventaId)->first());
    }

    public function test_administrador_puede_ver_venta_de_otro_usuario(): void
    {
        $admin = User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
        $vendedor = $this->createVendedor('ver-admin');
        $clienteId = $this->insertCliente();
        $cajaId = $this->insertCaja((int) $vendedor->id);
        $ventaId = $this->insertVentaActiva((int) $vendedor->id, $cajaId, $clienteId);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/ventas/{$ventaId}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $ventaId]);
    }
}
