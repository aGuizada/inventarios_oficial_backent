<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Flujos de negocio: artículos (CRUD + stock), ventas por unidad de medida, caja, anulación,
 * pagos mixtos, devolución (reingreso a inventario) y compras (contado/crédito + inventario + caja).
 */
class FlujoNegocioArticuloVentaCompraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@miempresa.com')->firstOrFail();
    }

    private function idSucursalMatriz(): int
    {
        return (int) DB::table('sucursales')->orderBy('id')->value('id');
    }

    private function medidaId(string $nombre): int
    {
        $id = (int) DB::table('medidas')->where('nombre_medida', $nombre)->value('id');

        return $id > 0 ? $id : throw new \RuntimeException("Medida no encontrada: {$nombre}");
    }

    private function tipoVentaIdContado(): int
    {
        $id = (int) DB::table('tipo_ventas')->where('nombre_tipo_ventas', 'contado')->value('id');

        return $id > 0 ? $id : throw new \RuntimeException('Tipo venta contado no encontrado.');
    }

    private function tipoVentaIdCredito(): int
    {
        foreach (['crédito', 'credito'] as $nombre) {
            $id = (int) DB::table('tipo_ventas')->where('nombre_tipo_ventas', $nombre)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        throw new \RuntimeException('Tipo venta crédito no encontrado.');
    }

    private function tipoPagoIdPorNombreContiene(string $fragment): int
    {
        $id = (int) DB::table('tipo_pagos')
            ->whereRaw('LOWER(nombre_tipo_pago) LIKE ?', ['%'.strtolower($fragment).'%'])
            ->orderBy('id')
            ->value('id');

        return $id > 0 ? $id : throw new \RuntimeException("Tipo pago no encontrado: {$fragment}");
    }

    /**
     * @return array{categoria_id: int, proveedor_id: int, marca_id: int, industria_id: int}
     */
    private function crearCatalogoMinimo(string $suffix): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Cat '.$suffix,
            'descripcion' => null,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proveedorId = DB::table('proveedores')->insertGetId([
            'nombre' => 'Prov '.$suffix,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $marcaId = DB::table('marcas')->insertGetId([
            'nombre' => substr('M '.$suffix, 0, 50),
            'logo' => null,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $industriaId = DB::table('industrias')->insertGetId([
            'nombre' => 'Ind '.$suffix,
            'estado' => 1,
        ]);

        return [
            'categoria_id' => $categoriaId,
            'proveedor_id' => $proveedorId,
            'marca_id' => $marcaId,
            'industria_id' => $industriaId,
        ];
    }

    private function insertCliente(string $suffix): int
    {
        return DB::table('clientes')->insertGetId([
            'nombre' => 'Cliente '.$suffix,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAlmacen(string $suffix): int
    {
        return DB::table('almacenes')->insertGetId([
            'nombre_almacen' => 'Alm '.$suffix,
            'sucursal_id' => $this->idSucursalMatriz(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCajaAbierta(int $userId, float $saldoInicial = 5000): int
    {
        return DB::table('cajas')->insertGetId([
            'sucursal_id' => $this->idSucursalMatriz(),
            'user_id' => $userId,
            'fecha_apertura' => now(),
            'fecha_cierre' => null,
            'saldo_inicial' => $saldoInicial,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function inventarioSaldoTotal(int $almacenId, int $articuloId): float
    {
        return round((float) DB::table('inventarios')
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
            ->sum('saldo_stock'), 3);
    }

    private function syncInventario(int $almacenId, int $articuloId, float $saldo): void
    {
        $existe = DB::table('inventarios')
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
            ->first();

        if ($existe) {
            DB::table('inventarios')->where('id', $existe->id)->update([
                'saldo_stock' => $saldo,
                'cantidad' => $saldo,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('inventarios')->insert([
            'almacen_id' => $almacenId,
            'articulo_id' => $articuloId,
            'fecha_vencimiento' => '2099-01-01',
            'saldo_stock' => $saldo,
            'cantidad' => $saldo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearArticuloApi(
        User $user,
        array $catalogo,
        string $nombre,
        int $medidaId,
        float $precioVenta,
        float $stockDeclarado,
        int $unidadEnvase = 1
    ): int {
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/articulos', [
            'categoria_id' => $catalogo['categoria_id'],
            'proveedor_id' => $catalogo['proveedor_id'],
            'medida_id' => $medidaId,
            'marca_id' => $catalogo['marca_id'],
            'industria_id' => $catalogo['industria_id'],
            'nombre' => $nombre,
            'precio_venta' => $precioVenta,
            'stock' => $stockDeclarado,
            'unidad_envase' => $unidadEnvase,
            'estado' => true,
        ]);

        $res->assertCreated();

        return (int) $res->json('id');
    }

    private function payloadVentaBase(
        int $clienteId,
        int $cajaId,
        int $almacenId,
        int $tipoVentaId,
        int $tipoPagoId,
        array $detalles,
        ?array $pagos = null
    ): array {
        $payload = [
            'cliente_id' => $clienteId,
            'tipo_venta_id' => $tipoVentaId,
            'tipo_pago_id' => $tipoPagoId,
            'tipo_comprobante' => 'ticket',
            'serie_comprobante' => null,
            'num_comprobante' => 'V-FLUJ-01',
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
            'caja_id' => $cajaId,
            'almacen_id' => $almacenId,
            'detalles' => $detalles,
        ];

        if ($pagos !== null) {
            $payload['pagos'] = $pagos;
        }

        return $payload;
    }

    public function test_crear_articulo_editar_stock_y_eliminar(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('art-crud');

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Producto CRUD',
            $this->medidaId('Unidad'),
            10.5,
            20
        );

        $this->assertGreaterThan(0, $articuloId);
        $this->assertSame(20.0, (float) DB::table('articulos')->where('id', $articuloId)->value('stock'));

        Sanctum::actingAs($admin);
        $upd = $this->putJson("/api/articulos/{$articuloId}", [
            'stock' => 77,
        ]);
        $upd->assertOk();
        $this->assertEqualsWithDelta(77.0, (float) DB::table('articulos')->where('id', $articuloId)->value('stock'), 0.01);

        $del = $this->deleteJson("/api/articulos/{$articuloId}");
        $del->assertStatus(204);
        $this->assertNull(DB::table('articulos')->where('id', $articuloId)->first());
    }

    public function test_venta_unidad_descuenta_inventario_y_suma_totales_en_caja_efectivo(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-u');
        $almacenId = $this->insertAlmacen('v-u');
        $clienteId = $this->insertCliente('v-u');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'SKU Unidad',
            $this->medidaId('Unidad'),
            15,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 100);

        $tipoPagoEfectivo = $this->tipoPagoIdPorNombreContiene('efectivo');

        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $tipoPagoEfectivo,
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 8,
                'precio' => 15,
                'descuento' => 0,
                'unidad_medida' => 'Unidad',
            ]]
        ));

        $res->assertCreated();
        $this->assertEqualsWithDelta(92.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertNotNull($caja);
        $totalEsperado = 8 * 15;
        $this->assertEqualsWithDelta($totalEsperado, (float) $caja->ventas, 0.01);
        $this->assertEqualsWithDelta($totalEsperado, (float) $caja->ventas_contado, 0.01);
        $this->assertEqualsWithDelta($totalEsperado, (float) $caja->pagos_efectivo, 0.01);
    }

    public function test_venta_paquete_usa_unidad_envase_para_descontar_stock(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-pq');
        $almacenId = $this->insertAlmacen('v-pq');
        $clienteId = $this->insertCliente('v-pq');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Caja x12',
            $this->medidaId('Paquete'),
            10,
            0,
            12
        );
        $this->syncInventario($almacenId, $articuloId, 100);

        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 2,
                'precio' => 10,
                'unidad_medida' => 'Paquete',
            ]]
        ));
        $res->assertCreated();

        // 2 paquetes × 12 unidades = 24
        $this->assertEqualsWithDelta(76.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);
    }

    public function test_venta_metro_y_centimetro_descuentan_en_unidad_base(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-mc');
        $almacenId = $this->insertAlmacen('v-mc');
        $clienteId = $this->insertCliente('v-mc');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloMetro = $this->crearArticuloApi(
            $admin,
            $cat,
            'Cable m',
            $this->medidaId('Metro'),
            5,
            0
        );
        $this->syncInventario($almacenId, $articuloMetro, 10.0);

        Sanctum::actingAs($admin);
        $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloMetro,
                'cantidad' => 2,
                'precio' => 5,
                'unidad_medida' => 'Metro',
            ]]
        ))->assertCreated();

        $this->assertEqualsWithDelta(8.0, $this->inventarioSaldoTotal($almacenId, $articuloMetro), 0.01);

        $articuloCm = $this->crearArticuloApi(
            $admin,
            $cat,
            'Tela cm',
            $this->medidaId('Centimetro'),
            3,
            0
        );
        $this->syncInventario($almacenId, $articuloCm, 5.0);

        $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloCm,
                'cantidad' => 150,
                'precio' => 2,
                'unidad_medida' => 'Centimetro',
            ]]
        ))->assertCreated();

        // 150 cm → 1.5 m base
        $this->assertEqualsWithDelta(3.5, $this->inventarioSaldoTotal($almacenId, $articuloCm), 0.01);
    }

    public function test_venta_rechaza_stock_insuficiente_422(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-stk');
        $almacenId = $this->insertAlmacen('v-stk');
        $clienteId = $this->insertCliente('v-stk');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Poco stock',
            $this->medidaId('Unidad'),
            1,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 1);

        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 5,
                'precio' => 1,
                'unidad_medida' => 'Unidad',
            ]]
        ));

        $res->assertStatus(422);
        $this->assertEqualsWithDelta(1.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);
    }

    public function test_venta_pagos_mixtos_distribuye_efectivo_y_qr_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-mix');
        $almacenId = $this->insertAlmacen('v-mix');
        $clienteId = $this->insertCliente('v-mix');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Mix',
            $this->medidaId('Unidad'),
            10,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 50);

        $idEfectivo = $this->tipoPagoIdPorNombreContiene('efectivo');
        $idQr = $this->tipoPagoIdPorNombreContiene('qr');

        $payload = $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $idEfectivo,
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 5,
                'precio' => 10,
                'unidad_medida' => 'Unidad',
            ]],
            [
                ['tipo_pago_id' => $idEfectivo, 'monto' => 30],
                ['tipo_pago_id' => $idQr, 'monto' => 20],
            ]
        );

        Sanctum::actingAs($admin);
        $this->postJson('/api/ventas', $payload)->assertCreated();

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertNotNull($caja);
        $this->assertEqualsWithDelta(30.0, (float) $caja->pagos_efectivo, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $caja->pagos_qr, 0.01);
    }

    public function test_venta_pago_transferencia_suma_pagos_transferencia_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-trf');
        $almacenId = $this->insertAlmacen('v-trf');
        $clienteId = $this->insertCliente('v-trf');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Prod transfer',
            $this->medidaId('Unidad'),
            20,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 30);

        $idTransferencia = $this->tipoPagoIdPorNombreContiene('transferencia');

        Sanctum::actingAs($admin);
        $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $idTransferencia,
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 2,
                'precio' => 20,
                'unidad_medida' => 'Unidad',
            ]]
        ))->assertCreated();

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertNotNull($caja);
        $this->assertEqualsWithDelta(40.0, (float) $caja->pagos_transferencia, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $caja->pagos_efectivo, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $caja->ventas_contado, 0.01);
    }

    public function test_venta_pagos_mixtos_efectivo_y_transferencia_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-mxt');
        $almacenId = $this->insertAlmacen('v-mxt');
        $clienteId = $this->insertCliente('v-mxt');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Mix transf',
            $this->medidaId('Unidad'),
            12,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 40);

        $idEfectivo = $this->tipoPagoIdPorNombreContiene('efectivo');
        $idTransferencia = $this->tipoPagoIdPorNombreContiene('transferencia');

        $payload = $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $idEfectivo,
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 5,
                'precio' => 12,
                'unidad_medida' => 'Unidad',
            ]],
            [
                ['tipo_pago_id' => $idEfectivo, 'monto' => 30],
                ['tipo_pago_id' => $idTransferencia, 'monto' => 30],
            ]
        );

        Sanctum::actingAs($admin);
        $this->postJson('/api/ventas', $payload)->assertCreated();

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertNotNull($caja);
        $this->assertEqualsWithDelta(30.0, (float) $caja->pagos_efectivo, 0.01);
        $this->assertEqualsWithDelta(30.0, (float) $caja->pagos_transferencia, 0.01);
    }

    public function test_venta_credito_clasifica_totales_en_ventas_credito_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-cr');
        $almacenId = $this->insertAlmacen('v-cr');
        $clienteId = $this->insertCliente('v-cr');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Cred',
            $this->medidaId('Unidad'),
            40,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 20);

        $idEfectivo = $this->tipoPagoIdPorNombreContiene('efectivo');

        Sanctum::actingAs($admin);
        $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdCredito(),
            $idEfectivo,
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 2,
                'precio' => 40,
                'unidad_medida' => 'Unidad',
            ]]
        ))->assertCreated();

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $total = 80.0;
        $this->assertNotNull($caja);
        $this->assertEqualsWithDelta($total, (float) $caja->ventas, 0.01);
        $this->assertEqualsWithDelta($total, (float) $caja->ventas_credito, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $caja->ventas_contado, 0.01);
        $this->assertEqualsWithDelta($total, (float) $caja->pagos_efectivo, 0.01);
    }

    public function test_anular_venta_revierte_inventario_y_saldos_de_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-an');
        $almacenId = $this->insertAlmacen('v-an');
        $clienteId = $this->insertCliente('v-an');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Anul',
            $this->medidaId('Unidad'),
            25,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 40);

        Sanctum::actingAs($admin);
        $cre = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 4,
                'precio' => 25,
                'unidad_medida' => 'Unidad',
            ]],
            null
        ));
        $cre->assertCreated();
        $ventaId = (int) $cre->json('id');

        $this->assertEqualsWithDelta(36.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);

        $cajaAntes = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertEqualsWithDelta(100.0, (float) $cajaAntes->ventas, 0.01);

        $an = $this->postJson("/api/ventas/{$ventaId}/anular");
        $an->assertOk();

        $this->assertSame('Anulado', DB::table('ventas')->where('id', $ventaId)->value('estado'));
        $this->assertEqualsWithDelta(40.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);

        $cajaDesp = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertEqualsWithDelta(0.0, (float) $cajaDesp->ventas, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $cajaDesp->ventas_contado, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $cajaDesp->pagos_efectivo, 0.01);

        // Detalle de caja (historial UI): totales recalculados no deben incluir la venta anulada
        $det = $this->getJson("/api/cajas/{$cajaId}/details");
        $det->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $det->json('calculado.total_ventas'), 0.01);
    }

    public function test_anular_sin_detalle_pagos_revierte_caja_usando_tipo_pago_de_cabecera(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('v-sin-pago');
        $almacenId = $this->insertAlmacen('v-sin-pago');
        $clienteId = $this->insertCliente('v-sin-pago');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Sin det pago',
            $this->medidaId('Unidad'),
            10,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 50);

        Sanctum::actingAs($admin);
        $cre = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 3,
                'precio' => 10,
                'unidad_medida' => 'Unidad',
            ]]
        ));
        $cre->assertCreated();
        $ventaId = (int) $cre->json('id');

        DB::table('detalle_pagos')->where('venta_id', $ventaId)->delete();

        $this->postJson("/api/ventas/{$ventaId}/anular")->assertOk();

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertEqualsWithDelta(0.0, (float) $caja->pagos_efectivo, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $caja->ventas_contado, 0.01);
    }

    public function test_devolucion_reingresa_cantidad_a_inventario(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('dev');
        $almacenId = $this->insertAlmacen('dev');
        $clienteId = $this->insertCliente('dev');
        $cajaId = $this->insertCajaAbierta((int) $admin->id);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Devol',
            $this->medidaId('Unidad'),
            10,
            0
        );
        $this->syncInventario($almacenId, $articuloId, 30);

        Sanctum::actingAs($admin);
        $cre = $this->postJson('/api/ventas', $this->payloadVentaBase(
            $clienteId,
            $cajaId,
            $almacenId,
            $this->tipoVentaIdContado(),
            $this->tipoPagoIdPorNombreContiene('efectivo'),
            [[
                'articulo_id' => $articuloId,
                'cantidad' => 5,
                'precio' => 10,
                'unidad_medida' => 'Unidad',
            ]]
        ));
        $cre->assertCreated();
        $ventaId = (int) $cre->json('id');

        $this->assertEqualsWithDelta(25.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);

        $dev = $this->postJson('/api/devoluciones', [
            'venta_id' => $ventaId,
            'fecha' => now()->format('Y-m-d'),
            'motivo' => 'Cliente arrepentido',
            'observaciones' => null,
            'detalles' => [[
                'articulo_id' => $articuloId,
                'almacen_id' => $almacenId,
                'cantidad' => 2,
                'precio_unitario' => 10,
            ]],
        ]);
        $dev->assertCreated();

        // Reingreso: 25 + 2 = 27
        $this->assertEqualsWithDelta(27.0, $this->inventarioSaldoTotal($almacenId, $articuloId), 0.01);
    }

    public function test_compra_contado_incrementa_inventario_y_compras_contado_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('co');
        $almacenId = $this->insertAlmacen('co');
        $cajaId = $this->insertCajaAbierta((int) $admin->id, 10000);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Compra SKU',
            $this->medidaId('Unidad'),
            5,
            0
        );

        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/compras', [
            'proveedor_nombre' => 'Prov compra test',
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
            'almacen_id' => $almacenId,
            'caja_id' => $cajaId,
            'tipo_compra' => 'contado',
            'num_comprobante' => 'C-FLUJ-01',
            'detalles' => [[
                'articulo_id' => $articuloId,
                'cantidad' => 7,
                'precio_unitario' => 12,
                'descuento' => 0,
            ]],
        ]);

        $res->assertCreated();
        $totalDinero = 7 * 12;

        // Inventario y stock del artículo: cantidades compradas (7 unidades), no el monto Bs.
        $this->assertEqualsWithDelta(
            7.0,
            $this->inventarioSaldoTotal($almacenId, $articuloId),
            0.01
        );
        $this->assertEqualsWithDelta(
            7.0,
            (float) DB::table('articulos')->where('id', $articuloId)->value('stock'),
            0.01
        );

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertEqualsWithDelta((float) $totalDinero, (float) $caja->compras_contado, 0.01);
    }

    public function test_compra_credito_incrementa_compras_credito_en_caja(): void
    {
        $admin = $this->adminUser();
        $cat = $this->crearCatalogoMinimo('ccr');
        $almacenId = $this->insertAlmacen('ccr');
        $cajaId = $this->insertCajaAbierta((int) $admin->id, 10000);

        $articuloId = $this->crearArticuloApi(
            $admin,
            $cat,
            'Compra cred',
            $this->medidaId('Unidad'),
            3,
            0
        );

        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/compras', [
            'proveedor_nombre' => 'Prov credito test',
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
            'almacen_id' => $almacenId,
            'caja_id' => $cajaId,
            'tipo_compra' => 'credito',
            'num_comprobante' => 'C-CR-01',
            'numero_cuotas' => 3,
            'monto_pagado' => 0,
            'detalles' => [[
                'articulo_id' => $articuloId,
                'cantidad' => 4,
                'precio_unitario' => 25,
                'descuento' => 0,
            ]],
        ]);

        $res->assertCreated();
        $total = 4 * 25;

        $caja = DB::table('cajas')->where('id', $cajaId)->first();
        $this->assertEqualsWithDelta((float) $total, (float) $caja->compras_credito, 0.01);
    }
}
