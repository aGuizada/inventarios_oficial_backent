<?php

namespace Tests\Feature;

use Tests\TestCase;

class VentaApiSecurityTest extends TestCase
{
    public function test_listar_ventas_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/ventas');

        $response->assertStatus(401);
    }

    public function test_anular_venta_requiere_autenticacion(): void
    {
        $response = $this->postJson('/api/ventas/1/anular');

        $response->assertStatus(401);
    }
}
