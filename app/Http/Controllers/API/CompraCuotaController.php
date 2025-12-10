<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompraCuota;
use Illuminate\Http\Request;

class CompraCuotaController extends Controller
{
    public function index()
    {
        $cuotas = CompraCuota::with('compraCredito')->get();
        return response()->json($cuotas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'compra_credito_id' => 'required|exists:compras_credito,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'monto_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $cuota = CompraCuota::create($request->all());

        return response()->json($cuota, 201);
    }

    public function show(CompraCuota $compraCuota)
    {
        $compraCuota->load('compraCredito');
        return response()->json($compraCuota);
    }

    public function update(Request $request, CompraCuota $compraCuota)
    {
        $request->validate([
            'compra_credito_id' => 'required|exists:compras_credito,id',
            'numero_cuota' => 'required|integer',
            'fecha_pago' => 'required|date',
            'fecha_cancelado' => 'nullable|date',
            'monto_cuota' => 'required|numeric',
            'saldo_restante' => 'required|numeric',
            'estado' => 'nullable|string|max:50',
        ]);

        $compraCuota->update($request->all());

        return response()->json($compraCuota);
    }

    public function destroy(CompraCuota $compraCuota)
    {
        $compraCuota->delete();
        return response()->json(null, 204);
    }

    public function pagarCuota(Request $request, $id)
    {
        try {
            $cuota = CompraCuota::findOrFail($id);
            
            $request->validate([
                'monto_pagado' => 'required|numeric|min:0',
                'fecha_pago' => 'nullable|date',
            ]);

            if ($cuota->estado === 'Pagado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya está pagada'
                ], 400);
            }

            $montoPagado = (float)$request->monto_pagado;
            $saldoAnterior = (float)$cuota->saldo_pendiente;
            
            // Actualizar cuota
            $cuota->monto_pagado = $montoPagado;
            $cuota->saldo_pendiente = max(0, $saldoAnterior - $montoPagado);
            $cuota->fecha_pago = $request->fecha_pago ? $request->fecha_pago : now()->format('Y-m-d');
            $cuota->estado = $cuota->saldo_pendiente <= 0 ? 'Pagado' : 'Parcial';
            $cuota->save();

            // Verificar si todas las cuotas están pagadas para actualizar el crédito
            $compraCredito = $cuota->compraCredito;
            if ($compraCredito) {
                $todasPagadas = $compraCredito->cuotas()
                    ->where('estado', '!=', 'Pagado')
                    ->count() === 0;
                
                if ($todasPagadas) {
                    $compraCredito->estado_credito = 'Pagado';
                    $compraCredito->save();
                }
            }

            $cuota->load('compraCredito');

            return response()->json([
                'success' => true,
                'data' => $cuota,
                'message' => 'Cuota pagada exitosamente'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al pagar cuota de compra:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al pagar cuota: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByCompraCredito($compraCreditoId)
    {
        try {
            $cuotas = CompraCuota::where('compra_credito_id', $compraCreditoId)
                ->orderBy('numero_cuota', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $cuotas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuotas: ' . $e->getMessage()
            ], 500);
        }
    }
}
