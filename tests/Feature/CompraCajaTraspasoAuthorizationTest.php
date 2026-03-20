<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompraCajaTraspasoAuthorizationTest extends TestCase
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

    private function insertAlmacen(string $nombre = 'Almacén test'): int
    {
        return DB::table('almacenes')->insertGetId([
            'nombre_almacen' => $nombre,
            'sucursal_id' => $this->idSucursalMatriz(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProveedor(): int
    {
        return DB::table('proveedores')->insertGetId([
            'nombre' => 'Proveedor Test',
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * `num_comprobante` en BD es string(10) — mantener ≤ 10 caracteres.
     */
    private function insertCompraBase(int $ownerUserId, int $cajaId, int $almacenId, int $proveedorId, string $numComprobante = 'C-TEST-01'): int
    {
        return DB::table('compras_base')->insertGetId([
            'proveedor_id' => $proveedorId,
            'user_id' => $ownerUserId,
            'tipo_comprobante' => 'ticket',
            'serie_comprobante' => null,
            'num_comprobante' => $numComprobante,
            'fecha_hora' => now(),
            'total' => 75.50,
            'estado' => 'Activo',
            'almacen_id' => $almacenId,
            'caja_id' => $cajaId,
            'descuento_global' => 0.00,
            'tipo_compra' => 'CONTADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTraspaso(int $ownerUserId, string $codigo, int $almacenOrigenId, int $almacenDestinoId): int
    {
        $sid = $this->idSucursalMatriz();

        return DB::table('traspasos')->insertGetId([
            'codigo_traspaso' => $codigo,
            'sucursal_origen_id' => $sid,
            'sucursal_destino_id' => $sid,
            'almacen_origen_id' => $almacenOrigenId,
            'almacen_destino_id' => $almacenDestinoId,
            'user_id' => $ownerUserId,
            'fecha_solicitud' => now(),
            'fecha_aprobacion' => null,
            'fecha_entrega' => null,
            'tipo_traspaso' => 'SUCURSAL',
            'estado' => 'PENDIENTE',
            'motivo' => null,
            'observaciones' => null,
            'usuario_aprobador_id' => null,
            'usuario_receptor_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assert403NotAuthorized($response): void
    {
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No autorizado para realizar esta acción.',
        ]);
    }

    // --- Compras ---

    public function test_vendedor_no_puede_ver_compra_de_otro_usuario(): void
    {
        $a = $this->createVendedor('co-a');
        $b = $this->createVendedor('co-b');
        $proveedorId = $this->insertProveedor();
        $cajaId = $this->insertCaja((int) $b->id);
        $almacenId = $this->insertAlmacen();
        $compraId = $this->insertCompraBase((int) $b->id, $cajaId, $almacenId, $proveedorId);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->getJson("/api/compras/{$compraId}"));
    }

    public function test_vendedor_no_puede_actualizar_compra_de_otro_usuario(): void
    {
        $a = $this->createVendedor('coh-a');
        $b = $this->createVendedor('coh-b');
        $proveedorId = $this->insertProveedor();
        $cajaId = $this->insertCaja((int) $b->id);
        $almacenId = $this->insertAlmacen();
        $compraId = $this->insertCompraBase((int) $b->id, $cajaId, $almacenId, $proveedorId, 'C-DNY-UPD');

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->putJson("/api/compras/{$compraId}", []));
    }

    public function test_vendedor_no_puede_eliminar_compra_de_otro_usuario(): void
    {
        $a = $this->createVendedor('coh-del-a');
        $b = $this->createVendedor('coh-del-b');
        $proveedorId = $this->insertProveedor();
        $cajaId = $this->insertCaja((int) $b->id);
        $almacenId = $this->insertAlmacen();
        $compraId = $this->insertCompraBase((int) $b->id, $cajaId, $almacenId, $proveedorId, 'C-DNY-DEL');

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->deleteJson("/api/compras/{$compraId}"));
        $this->assertNotNull(DB::table('compras_base')->where('id', $compraId)->first());
    }

    public function test_administrador_puede_ver_compra_de_otro_usuario(): void
    {
        $admin = User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
        $b = $this->createVendedor('co-adm');
        $proveedorId = $this->insertProveedor();
        $cajaId = $this->insertCaja((int) $b->id);
        $almacenId = $this->insertAlmacen();
        $compraId = $this->insertCompraBase((int) $b->id, $cajaId, $almacenId, $proveedorId, 'C-ADM-001');

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/compras/{$compraId}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $compraId);
    }

    // --- Cajas ---

    public function test_vendedor_no_puede_ver_caja_de_otro_usuario(): void
    {
        $a = $this->createVendedor('cj-a');
        $b = $this->createVendedor('cj-b');
        $cajaId = $this->insertCaja((int) $b->id);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->getJson("/api/cajas/{$cajaId}"));
    }

    public function test_vendedor_no_puede_actualizar_caja_de_otro_usuario(): void
    {
        $a = $this->createVendedor('cj-up-a');
        $b = $this->createVendedor('cj-up-b');
        $cajaId = $this->insertCaja((int) $b->id);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->putJson("/api/cajas/{$cajaId}", []));
    }

    public function test_vendedor_no_puede_eliminar_caja_de_otro_usuario(): void
    {
        $a = $this->createVendedor('cj-dl-a');
        $b = $this->createVendedor('cj-dl-b');
        $cajaId = $this->insertCaja((int) $b->id);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->deleteJson("/api/cajas/{$cajaId}"));
        $this->assertNotNull(DB::table('cajas')->where('id', $cajaId)->first());
    }

    public function test_vendedor_no_puede_ver_detalle_caja_de_otro_usuario(): void
    {
        $a = $this->createVendedor('cj-det-a');
        $b = $this->createVendedor('cj-det-b');
        $cajaId = $this->insertCaja((int) $b->id);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->getJson("/api/cajas/{$cajaId}/details"));
    }

    public function test_administrador_puede_ver_caja_de_otro_usuario(): void
    {
        $admin = User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
        $b = $this->createVendedor('cj-adm');
        $cajaId = $this->insertCaja((int) $b->id);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/cajas/{$cajaId}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $cajaId]);
    }

    // --- Traspasos ---

    public function test_vendedor_no_puede_ver_traspaso_de_otro_usuario(): void
    {
        $a = $this->createVendedor('tr-a');
        $b = $this->createVendedor('tr-b');
        $ao = $this->insertAlmacen('Alm origen');
        $ad = $this->insertAlmacen('Alm destino');
        $tid = $this->insertTraspaso((int) $b->id, 'TRP-DENY-SHOW', $ao, $ad);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->getJson("/api/traspasos/{$tid}"));
    }

    public function test_vendedor_no_puede_actualizar_traspaso_de_otro_usuario(): void
    {
        $a = $this->createVendedor('tr-u-a');
        $b = $this->createVendedor('tr-u-b');
        $ao = $this->insertAlmacen('Alm o2');
        $ad = $this->insertAlmacen('Alm d2');
        $tid = $this->insertTraspaso((int) $b->id, 'TRP-DENY-UPD', $ao, $ad);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->putJson("/api/traspasos/{$tid}", []));
    }

    public function test_vendedor_no_puede_eliminar_traspaso_de_otro_usuario(): void
    {
        $a = $this->createVendedor('tr-d-a');
        $b = $this->createVendedor('tr-d-b');
        $ao = $this->insertAlmacen('Alm o3');
        $ad = $this->insertAlmacen('Alm d3');
        $tid = $this->insertTraspaso((int) $b->id, 'TRP-DENY-DEL', $ao, $ad);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->deleteJson("/api/traspasos/{$tid}"));
        $this->assertNotNull(DB::table('traspasos')->where('id', $tid)->first());
    }

    public function test_vendedor_no_puede_aprobar_traspaso_de_otro_usuario(): void
    {
        $a = $this->createVendedor('tr-ap-a');
        $b = $this->createVendedor('tr-ap-b');
        $ao = $this->insertAlmacen('Alm o4');
        $ad = $this->insertAlmacen('Alm d4');
        $tid = $this->insertTraspaso((int) $b->id, 'TRP-DENY-APR', $ao, $ad);

        Sanctum::actingAs($a);
        $this->assert403NotAuthorized($this->postJson("/api/traspasos/{$tid}/aprobar", []));
    }

    public function test_administrador_puede_ver_traspaso_de_otro_usuario(): void
    {
        $admin = User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
        $b = $this->createVendedor('tr-adm');
        $ao = $this->insertAlmacen('Alm o5');
        $ad = $this->insertAlmacen('Alm d5');
        $tid = $this->insertTraspaso((int) $b->id, 'TRP-ADM', $ao, $ad);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/traspasos/{$tid}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $tid]);
    }
}
