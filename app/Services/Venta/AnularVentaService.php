<?php

namespace App\Services\Venta;

use App\Models\Caja;
use App\Models\TipoPago;
use App\Models\User;
use App\Models\Venta;
use App\Services\KardexService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Caso de uso: anular venta activa (kardex, caja, crédito, estado).
 * Extraído del controlador para cumplir SRP y facilitar pruebas.
 */
final class AnularVentaService
{
    public function __construct(
        private readonly KardexService $kardexService
    ) {}

    /**
     * @throws Throwable
     */
    public function anular(Venta $venta, ?User $user, int $almacenId): Venta
    {
        return DB::transaction(function () use ($venta, $user, $almacenId) {
            foreach ($venta->detalles as $detalle) {
                $articulo = $detalle->articulo;
                $unidadMedida = $detalle->unidad_medida ?? 'Unidad';
                $cantidadReintegrar = \App\Support\VentaCantidadConverter::toUnidadBaseByArticuloId(
                    (int) $detalle->articulo_id,
                    (float) $detalle->cantidad,
                    $unidadMedida
                );

                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $detalle->articulo_id,
                    'almacen_id' => $almacenId,
                    'fecha' => now(),
                    'tipo_movimiento' => 'anulacion_venta',
                    'documento_tipo' => $venta->tipo_comprobante ?? 'ticket',
                    'documento_numero' => $venta->num_comprobante ?? 'S/N',
                    'cantidad_entrada' => $cantidadReintegrar,
                    'cantidad_salida' => 0,
                    'costo_unitario' => $articulo->precio_costo ?? 0,
                    'precio_unitario' => (float) $detalle->precio,
                    'observaciones' => 'Anulación de venta #'.$venta->id,
                    'usuario_id' => $user ? $user->id : $venta->user_id,
                    'venta_id' => $venta->id,
                ]);
            }

            if ($venta->caja_id) {
                $venta->loadMissing(['pagos.tipoPago', 'tipoVenta', 'tipoPago']);

                $caja = Caja::find($venta->caja_id);
                if ($caja) {
                    $totalVenta = (float) $venta->total;
                    $caja->ventas = max(0, (float) ($caja->ventas ?? 0) - $totalVenta);

                    $tipoVenta = $venta->tipoVenta;
                    $nombreTipoVenta = $tipoVenta ? strtolower(trim((string) ($tipoVenta->nombre_tipo_ventas ?? ''))) : '';
                    if (strpos($nombreTipoVenta, 'contado') !== false) {
                        $caja->ventas_contado = max(0, (float) ($caja->ventas_contado ?? 0) - $totalVenta);
                    } elseif (strpos($nombreTipoVenta, 'crédito') !== false || strpos($nombreTipoVenta, 'credito') !== false) {
                        $caja->ventas_credito = max(0, (float) ($caja->ventas_credito ?? 0) - $totalVenta);
                    }

                    if ($venta->pagos->isNotEmpty()) {
                        foreach ($venta->pagos as $pago) {
                            $this->revertirMontoSegunTipoPago($caja, $pago->tipoPago, (float) $pago->monto);
                        }
                    } elseif ($venta->tipo_pago_id && $venta->tipoPago) {
                        // Ventas sin filas en detalle_pagos: usar cabecera (histórico u otros flujos)
                        $this->revertirMontoSegunTipoPago($caja, $venta->tipoPago, $totalVenta);
                    }

                    $caja->save();
                }
            }

            if ($venta->credito) {
                $venta->credito->update(['estado' => 'Anulado']);
            }

            $venta->estado = 'Anulado';
            $venta->save();

            Cache::forget('dashboard.kpis');
            Cache::forget('dashboard.ventas_recientes');

            $venta->load(['cliente', 'user', 'tipoVenta', 'tipoPago', 'caja', 'pagos.tipoPago']);

            return $venta;
        });
    }

    private function revertirMontoSegunTipoPago(Caja $caja, ?TipoPago $tipoPago, float $monto): void
    {
        if ($monto <= 0) {
            return;
        }

        $nombre = $tipoPago ? strtolower(trim((string) ($tipoPago->nombre_tipo_pago ?? ''))) : '';

        if (strpos($nombre, 'efectivo') !== false) {
            $caja->pagos_efectivo = max(0, (float) ($caja->pagos_efectivo ?? 0) - $monto);

            return;
        }
        if (strpos($nombre, 'qr') !== false) {
            $caja->pagos_qr = max(0, (float) ($caja->pagos_qr ?? 0) - $monto);

            return;
        }
        if (strpos($nombre, 'transferencia') !== false) {
            $caja->pagos_transferencia = max(0, (float) ($caja->pagos_transferencia ?? 0) - $monto);
        }
    }
}
