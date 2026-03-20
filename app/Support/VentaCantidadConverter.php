<?php

namespace App\Support;

use App\Models\Articulo;

/**
 * Convierte cantidades de venta/kardex a unidad base (coherente entre store, edición y anulación).
 */
final class VentaCantidadConverter
{
    /**
     * @param  \App\Models\Articulo|null  $articulo  Requerido para conversión correcta en "Paquete"
     */
    public static function toUnidadBase(?Articulo $articulo, float $cantidadVenta, string $unidadMedida): float
    {
        $unidadMedida = $unidadMedida !== '' ? $unidadMedida : 'Unidad';
        $cantidadDeducir = $cantidadVenta;

        if ($unidadMedida === 'Paquete' && $articulo) {
            $unidadEnvase = (float) ($articulo->unidad_envase ?? 1);
            $cantidadDeducir = $cantidadVenta * ($unidadEnvase > 0 ? $unidadEnvase : 1);
        } elseif ($unidadMedida === 'Centimetro') {
            $cantidadDeducir = $cantidadVenta / 100;
        } elseif ($unidadMedida === 'Metro') {
            $cantidadDeducir = $cantidadVenta;
        }

        return round((float) $cantidadDeducir, 3);
    }

    public static function toUnidadBaseByArticuloId(int $articuloId, float $cantidadVenta, string $unidadMedida): float
    {
        $articulo = Articulo::find($articuloId);

        return self::toUnidadBase($articulo, $cantidadVenta, $unidadMedida);
    }
}
