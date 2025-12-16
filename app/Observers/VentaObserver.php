<?php

namespace App\Observers;

use App\Models\Venta;
use App\Models\Inventario;
use App\Notifications\LowStockNotification;
use App\Notifications\OutOfStockNotification;
use App\Notifications\CreditSaleNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Log;

class VentaObserver
{
    /**
     * Handle the Venta "created" event.
     */
    public function created(Venta $venta): void
    {
        Log::info('VentaObserver triggered for venta_id: ' . $venta->id);

        // Check if it's a credit sale
        if ($venta->tipo_venta_id) {
            $venta->load('tipoVenta', 'cliente');

            if ($venta->tipoVenta && strtolower($venta->tipoVenta->nombre) === 'crédito') {
                // Notify admins about credit sale
                $cliente = $venta->cliente;
                if ($cliente) {
                    Log::info('Sending credit sale notification');
                    NotificationHelper::notifyAdmins(new CreditSaleNotification($venta, $cliente));
                }
            }
        }

        // Check stock levels for all products in the sale
        $this->checkStockLevels($venta);
    }

    /**
     * Check stock levels after a sale and notify if low or out of stock
     */
    private function checkStockLevels(Venta $venta): void
    {
        // Load detalles relationship
        $venta->load('detalles.articulo');

        if (!$venta->detalles || $venta->detalles->isEmpty()) {
            Log::warning('No detalles found for venta_id: ' . $venta->id);
            return;
        }

        Log::info('Checking stock for ' . $venta->detalles->count() . ' products');

        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            if (!$articulo) {
                Log::warning('Articulo not found for detalle_id: ' . $detalle->id);
                continue;
            }

            // Get current inventory for this product
            $inventario = Inventario::where('articulo_id', $articulo->id)
                ->where('almacen_id', $venta->almacen_id)
                ->first();

            if (!$inventario) {
                Log::warning('Inventario not found for articulo_id: ' . $articulo->id . ' in almacen_id: ' . $venta->almacen_id);
                continue;
            }

            $currentStock = $inventario->stock_actual;
            Log::info("Articulo '{$articulo->nombre}' - Stock actual: {$currentStock}, Stock mínimo: " . ($articulo->stock_minimo ?? 'N/A'));

            // Check if out of stock
            if ($currentStock <= 0) {
                Log::info("Sending OUT OF STOCK notification for '{$articulo->nombre}'");
                NotificationHelper::notifyAdmins(new OutOfStockNotification($articulo));
                continue;
            }

            // Check if low stock
            if ($articulo->stock_minimo && $currentStock <= $articulo->stock_minimo) {
                Log::info("Sending LOW STOCK notification for '{$articulo->nombre}'");
                NotificationHelper::notifyAdmins(new LowStockNotification($articulo, $currentStock));
            }
        }
    }
}
