<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CuotaCredito;
use Illuminate\Http\Request;

class CuotaCreditoController extends Controller
{
    public function index()
    {
        $cuotas = CuotaCredito::with(['credito', 'cobrador'])->get();
        return response()->json($cuotas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'credito_id' => 'required|exists:credito_ventas,id',
            'cobrador_id' => 'nullable|exists:users,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'precio_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuota = CuotaCredito::create($request->all());

        return response()->json($cuota, 201);
    }

    public function show(CuotaCredito $cuotaCredito)
    {
        $cuotaCredito->load(['credito', 'cobrador']);
        return response()->json($cuotaCredito);
    }

    public function update(Request $request, CuotaCredito $cuotaCredito)
    {
        $request->validate([
            'credito_id' => 'required|exists:credito_ventas,id',
            'cobrador_id' => 'nullable|exists:users,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'precio_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuotaCredito->update($request->all());

        return response()->json($cuotaCredito);
    }

    public function destroy(CuotaCredito $cuotaCredito)
    {
        $cuotaCredito->delete();
        return response()->json(null, 204);
    }

    public function pagarCuota(Request $request, $id)
    {
        try {
            $cuota = CuotaCredito::findOrFail($id);
            
            $request->validate([
                'monto' => 'required|numeric|min:0',
                'cobrador_id' => 'nullable|exists:users,id',
            ]);

            if ($cuota->estado === 'Pagado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya está pagada'
                ], 400);
            }

            // Actualizar cuota
            $cuota->estado = 'Pagado';
            $cuota->fecha_cancelado = now();
            $cuota->cobrador_id = $request->cobrador_id ?? auth()->id();
            $cuota->saldo_restante = max(0, $cuota->saldo_restante - $request->monto);
            $cuota->save();

            // Verificar si todas las cuotas están pagadas para actualizar el crédito
            $credito = $cuota->credito;
            $todasPagadas = $credito->cuotas()->where('estado', '!=', 'Pagado')->count() === 0;
            if ($todasPagadas) {
                $credito->estado = 'Pagado';
                $credito->save();
            }

            $cuota->load(['credito', 'cobrador']);

            return response()->json([
                'success' => true,
                'data' => $cuota,
                'message' => 'Cuota pagada exitosamente'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al pagar cuota:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al pagar cuota: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByCredito($creditoId)
    {
        try {
            $cuotas = CuotaCredito::where('credito_id', $creditoId)
                ->with(['credito', 'cobrador'])
                ->orderBy('numero_cuota')
                ->get();
            
            // Si no hay cuotas, intentar generarlas automáticamente
            if ($cuotas->count() === 0) {
                $credito = \App\Models\CreditoVenta::find($creditoId);
                if ($credito) {
                    \Log::info('No hay cuotas, generándolas automáticamente desde getByCredito', ['credito_id' => $creditoId]);
                    $this->generarCuotas($credito);
                    // Recargar las cuotas después de generarlas
                    $cuotas = CuotaCredito::where('credito_id', $creditoId)
                        ->with(['credito', 'cobrador'])
                        ->orderBy('numero_cuota')
                        ->get();
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $cuotas
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener cuotas:', [
                'credito_id' => $creditoId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuotas',
                'data' => []
            ], 500);
        }
    }

    private function generarCuotas(\App\Models\CreditoVenta $creditoVenta): void
    {
        // Verificar si ya existen cuotas
        $cuotasExistentes = CuotaCredito::where('credito_id', $creditoVenta->id)->count();
        if ($cuotasExistentes > 0) {
            \Log::info('Las cuotas ya existen para este crédito', ['credito_id' => $creditoVenta->id, 'cuotas' => $cuotasExistentes]);
            return;
        }

        $numeroCuotas = $creditoVenta->numero_cuotas;
        $tiempoDiasCuota = $creditoVenta->tiempo_dias_cuota;
        $total = $creditoVenta->total;
        $montoPorCuota = $total / $numeroCuotas;

        // Calcular fecha base
        $fechaBase = \Carbon\Carbon::now();
        if ($creditoVenta->proximo_pago) {
            $fechaBase = \Carbon\Carbon::parse($creditoVenta->proximo_pago);
        }

        \Log::info('Generando cuotas para crédito', [
            'credito_id' => $creditoVenta->id,
            'numero_cuotas' => $numeroCuotas,
            'tiempo_dias_cuota' => $tiempoDiasCuota,
            'total' => $total,
            'monto_por_cuota' => $montoPorCuota
        ]);

        for ($i = 1; $i <= $numeroCuotas; $i++) {
            $fechaPago = $fechaBase->copy()->addDays(($i - 1) * $tiempoDiasCuota);
            $saldoRestante = max(0, $total - ($montoPorCuota * ($i - 1)));

            CuotaCredito::create([
                'credito_id' => $creditoVenta->id,
                'numero_cuota' => $i,
                'fecha_pago' => $fechaPago->format('Y-m-d H:i:s'),
                'precio_cuota' => round($montoPorCuota, 2),
                'saldo_restante' => round($saldoRestante, 2),
                'estado' => 'Pendiente'
            ]);
        }

        \Log::info('Cuotas generadas exitosamente', ['credito_id' => $creditoVenta->id, 'cantidad' => $numeroCuotas]);
    }
}
