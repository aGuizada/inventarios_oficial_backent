<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CompraBase;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;

class ActualizarInventarioDesdeCompras extends Command
{
    protected $signature = 'inventario:actualizar-desde-compras';
    protected $description = 'Actualiza el inventario basándose en todas las compras existentes';

    public function handle()
    {
        $this->info('Iniciando actualización de inventario desde compras...');

        DB::beginTransaction();
        try {
            // Obtener todas las compras
            $compras = CompraBase::with('detalles')->get();
            
            $this->info("Procesando {$compras->count()} compras...");

            $inventariosCreados = 0;
            $inventariosActualizados = 0;
            $errores = 0;

            foreach ($compras as $compra) {
                $this->line("Procesando compra ID: {$compra->id}");

                foreach ($compra->detalles as $detalle) {
                    $articuloId = $detalle->articulo_id;
                    $almacenId = $compra->almacen_id;
                    $cantidad = $detalle->cantidad;

                    // Verificar que el artículo existe
                    $articulo = Articulo::find($articuloId);
                    if (!$articulo) {
                        $this->warn("  ⚠ Artículo ID {$articuloId} no encontrado, saltando...");
                        $errores++;
                        continue;
                    }

                    // Buscar o crear registro de inventario
                    $inventario = Inventario::where('almacen_id', $almacenId)
                        ->where('articulo_id', $articuloId)
                        ->first();

                    if ($inventario) {
                        // Si existe, actualizar cantidad y saldo_stock
                        $inventario->cantidad += $cantidad;
                        $inventario->saldo_stock += $cantidad;
                        $inventario->save();
                        $inventariosActualizados++;
                        $this->line("  ✓ Inventario actualizado: Almacén {$almacenId}, Artículo {$articuloId}, Cantidad: {$cantidad}");
                    } else {
                        // Si no existe, crear nuevo registro
                        Inventario::create([
                            'almacen_id' => $almacenId,
                            'articulo_id' => $articuloId,
                            'cantidad' => $cantidad,
                            'saldo_stock' => $cantidad,
                            'fecha_vencimiento' => '2099-01-01',
                        ]);
                        $inventariosCreados++;
                        $this->line("  ✓ Nuevo inventario creado: Almacén {$almacenId}, Artículo {$articuloId}, Cantidad: {$cantidad}");
                    }

                    // Actualizar stock del artículo (stock general)
                    $articulo->stock += $cantidad;
                    $articulo->save();
                }
            }

            DB::commit();

            $this->info("\n✅ Proceso completado:");
            $this->info("  - Inventarios creados: {$inventariosCreados}");
            $this->info("  - Inventarios actualizados: {$inventariosActualizados}");
            if ($errores > 0) {
                $this->warn("  - Errores: {$errores}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error al actualizar inventario: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}















