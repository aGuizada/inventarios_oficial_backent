<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConteoFisico;
use App\Models\DetalleConteoFisico;
use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConteoFisicoController extends Controller
{
    /**
     * Listar conteos
     */
    public function index(Request $request)
    {
        $query = ConteoFisico::with(['almacen', 'usuario']);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $conteos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conteos
        ]);
    }

    /**
     * Crear nuevo conteo físico
     */
    public function store(Request $request)
    {
        $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_conteo' => 'required|date',
            'responsable' => 'required|string|max:100',
            'observaciones' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Crear conteo
            $conteo = ConteoFisico::create([
                'almacen_id' => $request->almacen_id,
                'fecha_conteo' => $request->fecha_conteo,
                'responsable' => $request->responsable,
                'estado' => 'en_proceso',
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id() ?? 1
            ]);

            // Obtener todos los artículos del almacén
            $inventarios = Inventario::where('almacen_id', $request->almacen_id)
                ->with('articulo')
                ->get();

            // Crear detalles con stock del sistema
            foreach ($inventarios as $inv) {
                DetalleConteoFisico::create([
                    'conteo_fisico_id' => $conteo->id,
                    'articulo_id' => $inv->articulo_id,
                    'cantidad_sistema' => $inv->saldo_stock,
                    'cantidad_contada' => null,
                    'diferencia' => 0,
                    'costo_unitario' => $inv->articulo->precio_costo ?? 0
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conteo físico creado exitosamente',
                'data' => $conteo->load('detalles.articulo')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear conteo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de conteo
     */
    public function show($id)
    {
        $conteo = ConteoFisico::with(['almacen', 'usuario', 'detalles.articulo'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $conteo
        ]);
    }

    /**
     * Actualizar cantidades contadas
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'detalles' => 'required|array',
            'detalles.*.id' => 'required|exists:detalle_conteos_fisicos,id',
            'detalles.*.cantidad_contada' => 'required|numeric|min:0'
        ]);

        $conteo = ConteoFisico::findOrFail($id);

        if ($conteo->estado !== 'en_proceso') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden editar conteos en proceso'
            ], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($request->detalles as $detalleData) {
                $detalle = DetalleConteoFisico::find($detalleData['id']);
                $detalle->cantidad_contada = $detalleData['cantidad_contada'];
                $detalle->diferencia = $detalleData['cantidad_contada'] - $detalle->cantidad_sistema;
                $detalle->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cantidades actualizadas',
                'data' => $conteo->load('detalles.articulo')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar ajustes automáticos por diferencias
     */
    public function generarAjustes($id)
    {
        $conteo = ConteoFisico::with('detalles.articulo')->findOrFail($id);

        if ($conteo->estado !== 'en_proceso') {
            return response()->json([
                'success' => false,
                'message' => 'El conteo ya fue procesado'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $ajustesCreados = 0;

            foreach ($conteo->detalles as $detalle) {
                if ($detalle->diferencia != 0 && $detalle->cantidad_contada !== null) {
                    // Actualizar inventario
                    $inventario = Inventario::where('articulo_id', $detalle->articulo_id)
                        ->where('almacen_id', $conteo->almacen_id)
                        ->first();

                    if ($inventario) {
                        $inventario->cantidad += $detalle->diferencia;
                        $inventario->saldo_stock += $detalle->diferencia;
                        $inventario->save();
                    }

                    // Actualizar artículo
                    $articulo = Articulo::find($detalle->articulo_id);
                    if ($articulo) {
                        $articulo->stock += $detalle->diferencia;
                        $articulo->save();
                    }

                    // Crear registro kardex
                    $saldoAnterior = Kardex::where('articulo_id', $detalle->articulo_id)
                        ->where('almacen_id', $conteo->almacen_id)
                        ->orderBy('fecha', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    $nuevoSaldo = ($saldoAnterior->cantidad_saldo ?? 0) + $detalle->diferencia;

                    Kardex::create([
                        'fecha' => $conteo->fecha_conteo,
                        'tipo_movimiento' => 'ajuste',
                        'documento_tipo' => 'conteo_fisico',
                        'documento_numero' => 'CF-' . $conteo->id,
                        'articulo_id' => $detalle->articulo_id,
                        'almacen_id' => $conteo->almacen_id,
                        'cantidad_entrada' => $detalle->diferencia > 0 ? $detalle->diferencia : 0,
                        'cantidad_salida' => $detalle->diferencia < 0 ? abs($detalle->diferencia) : 0,
                        'cantidad_saldo' => $nuevoSaldo,
                        'costo_unitario' => $detalle->costo_unitario,
                        'costo_total' => abs($detalle->diferencia) * $detalle->costo_unitario,
                        'observaciones' => 'Ajuste por conteo físico #' . $conteo->id . ' - ' . $detalle->articulo->nombre,
                        'usuario_id' => auth()->id() ?? 1
                    ]);

                    $ajustesCreados++;
                }
            }

            // Marcar conteo como finalizado
            $conteo->estado = 'finalizado';
            $conteo->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Conteo finalizado. Se generaron $ajustesCreados ajustes automáticos",
                'data' => [
                    'conteo' => $conteo,
                    'ajustes_creados' => $ajustesCreados
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error generando ajustes de conteo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generando ajustes: ' . $e->getMessage()
            ], 500);
        }
    }
}
