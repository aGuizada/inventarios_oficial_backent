<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Caja;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Caja::with(['sucursal', 'user']);

        // Campos buscables: ID, sucursal, usuario, fechas
        $searchableFields = [
            'id',
            'sucursal.nombre',
            'user.name',
            'user.email',
            'fecha_apertura',
            'fecha_cierre'
        ];

        // Aplicar búsqueda
        $query = $this->applySearch($query, $request, $searchableFields);

        // Campos ordenables
        $sortableFields = ['id', 'fecha_apertura', 'fecha_cierre', 'saldo_inicial', 'estado'];

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');

        // Aplicar paginación
        $response = $this->paginateResponse($query, $request, 15, 100);
        
        // Calcular saldo disponible para cada caja en la respuesta
        if (isset($response->original['data']['data'])) {
            foreach ($response->original['data']['data'] as &$caja) {
                // Calcular saldo disponible
                $saldoInicial = (float) ($caja['saldo_inicial'] ?? 0);
                $depositos = (float) ($caja['depositos'] ?? 0);
                $ventas = (float) ($caja['ventas'] ?? 0);
                $pagosEfectivo = (float) ($caja['pagos_efectivo'] ?? 0);
                $pagosQr = (float) ($caja['pagos_qr'] ?? 0);
                $pagosTransferencia = (float) ($caja['pagos_transferencia'] ?? 0);
                $cuotasVentasCredito = (float) ($caja['cuotas_ventas_credito'] ?? 0);
                $salidas = (float) ($caja['salidas'] ?? 0);
                $comprasContado = (float) ($caja['compras_contado'] ?? 0);
                $comprasCredito = (float) ($caja['compras_credito'] ?? 0);
                $saldoFaltante = (float) ($caja['saldo_faltante'] ?? 0);
                
                $saldoDisponible = $saldoInicial + $depositos + $ventas + $pagosEfectivo + $pagosQr + 
                                  $pagosTransferencia + $cuotasVentasCredito - $salidas - 
                                  $comprasContado - $comprasCredito - $saldoFaltante;
                
                // Asegurar que el saldo no sea negativo (si es negativo, mostrar 0)
                $saldoDisponible = max(0, round($saldoDisponible, 2));
                
                // Actualizar el saldo_caja en el objeto caja
                $caja['saldo_caja'] = $saldoDisponible;
            }
        }
        
        return $response;
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'sucursal_id' => 'required|exists:sucursales,id',
                'user_id' => 'required|exists:users,id',
                'fecha_apertura' => 'required|date',
                'fecha_cierre' => 'nullable|date',
                'saldo_inicial' => 'required|numeric',
                'depositos' => 'nullable|numeric',
                'salidas' => 'nullable|numeric',
                'ventas' => 'nullable|numeric',
                'ventas_contado' => 'nullable|numeric',
                'ventas_credito' => 'nullable|numeric',
                'pagos_efectivo' => 'nullable|numeric',
                'pagos_qr' => 'nullable|numeric',
                'pagos_transferencia' => 'nullable|numeric',
                'cuotas_ventas_credito' => 'nullable|numeric',
                'compras_contado' => 'nullable|numeric',
                'compras_credito' => 'nullable|numeric',
                'saldo_faltante' => 'nullable|numeric',
                'saldo_caja' => 'nullable|numeric',
                'estado' => 'boolean',
            ], [
                'sucursal_id.required' => 'La sucursal es obligatoria.',
                'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
                'user_id.required' => 'El usuario es obligatorio.',
                'user_id.exists' => 'El usuario seleccionado no existe.',
                'fecha_apertura.required' => 'La fecha de apertura es obligatoria.',
                'fecha_apertura.date' => 'La fecha de apertura debe ser una fecha válida.',
                'fecha_cierre.date' => 'La fecha de cierre debe ser una fecha válida.',
                'saldo_inicial.required' => 'El saldo inicial es obligatorio.',
                'saldo_inicial.numeric' => 'El saldo inicial debe ser un número.',
                'depositos.numeric' => 'Los depósitos deben ser un número.',
                'salidas.numeric' => 'Las salidas deben ser un número.',
                'ventas.numeric' => 'Las ventas deben ser un número.',
                'ventas_contado.numeric' => 'Las ventas al contado deben ser un número.',
                'ventas_credito.numeric' => 'Las ventas a crédito deben ser un número.',
                'pagos_efectivo.numeric' => 'Los pagos en efectivo deben ser un número.',
                'pagos_qr.numeric' => 'Los pagos QR deben ser un número.',
                'pagos_transferencia.numeric' => 'Los pagos por transferencia deben ser un número.',
                'cuotas_ventas_credito.numeric' => 'Las cuotas de ventas a crédito deben ser un número.',
                'compras_contado.numeric' => 'Las compras al contado deben ser un número.',
                'compras_credito.numeric' => 'Las compras a crédito deben ser un número.',
                'saldo_faltante.numeric' => 'El saldo faltante debe ser un número.',
                'saldo_caja.numeric' => 'El saldo de caja debe ser un número.',
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
            ]);

            // Validar que no exista una caja abierta para la misma sucursal
            $sucursalId = $request->sucursal_id;
            $estado = $request->estado ?? $request->input('estado');
            
            // Verificar si se está intentando abrir una caja (estado = 1, '1', true, o 'abierta')
            $isOpeningCaja = $estado === 1 || 
                            $estado === '1' || 
                            $estado === true || 
                            $estado === 'abierta' ||
                            (is_null($estado) && !$request->has('fecha_cierre')); // Si no tiene fecha_cierre, se considera apertura
            
            if ($isOpeningCaja) {
                // Buscar si ya existe una caja abierta para esta sucursal
                // Una caja está abierta si:
                // 1. No tiene fecha_cierre (NULL)
                // 2. Y tiene estado que indica abierta (1, '1', true, 'abierta')
                // 3. Y NO tiene estado que indica cerrada (0, '0', false, 'cerrada')
                $cajaAbierta = Caja::with('sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->whereNull('fecha_cierre') // Las cajas cerradas normalmente tienen fecha_cierre
                    ->where(function($query) {
                        // Solo considerar estados que indican caja abierta
                        $query->where('estado', 1)
                              ->orWhere('estado', '1')
                              ->orWhere('estado', true)
                              ->orWhere('estado', 'abierta');
                    })
                    // Excluir explícitamente estados que indican caja cerrada
                    ->where(function($query) {
                        $query->where('estado', '!=', 0)
                              ->where('estado', '!=', '0')
                              ->where('estado', '!=', false)
                              ->where('estado', '!=', 'cerrada');
                    })
                    ->first();
                
                if ($cajaAbierta) {
                    $sucursalNombre = $cajaAbierta->sucursal ? $cajaAbierta->sucursal->nombre : 'esta sucursal';
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => [
                            'sucursal_id' => [
                                "Ya existe una caja abierta para {$sucursalNombre}. Por favor, cierre la caja existente antes de abrir una nueva en la misma sucursal."
                            ]
                        ]
                    ], 422);
                }
            }

            $caja = Caja::create($request->all());
            $caja->load(['sucursal', 'user']);

            return response()->json($caja, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $caja = Caja::find($id);

        if (!$caja) {
            return response()->json([
                'message' => 'Caja no encontrada',
                'error' => "No se encontró una caja con el ID: {$id}"
            ], 404);
        }

        $caja->load(['sucursal', 'user']);
        
        // Calcular saldo disponible
        $saldoInicial = (float) ($caja->saldo_inicial ?? 0);
        $depositos = (float) ($caja->depositos ?? 0);
        $ventas = (float) ($caja->ventas ?? 0);
        $pagosEfectivo = (float) ($caja->pagos_efectivo ?? 0);
        $pagosQr = (float) ($caja->pagos_qr ?? 0);
        $pagosTransferencia = (float) ($caja->pagos_transferencia ?? 0);
        $cuotasVentasCredito = (float) ($caja->cuotas_ventas_credito ?? 0);
        $salidas = (float) ($caja->salidas ?? 0);
        $comprasContado = (float) ($caja->compras_contado ?? 0);
        $comprasCredito = (float) ($caja->compras_credito ?? 0);
        $saldoFaltante = (float) ($caja->saldo_faltante ?? 0);
        
        $saldoDisponible = $saldoInicial + $depositos + $ventas + $pagosEfectivo + $pagosQr + 
                          $pagosTransferencia + $cuotasVentasCredito - $salidas - 
                          $comprasContado - $comprasCredito - $saldoFaltante;
        
        // Actualizar el saldo_caja en el objeto caja
        $caja->saldo_caja = round($saldoDisponible, 2);
        
        return response()->json($caja);
    }

    public function update(Request $request, $id)
    {
        $caja = Caja::find($id);

        if (!$caja) {
            return response()->json([
                'message' => 'Caja no encontrada',
                'error' => "No se encontró una caja con el ID: {$id}"
            ], 404);
        }

        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'user_id' => 'required|exists:users,id',
            'fecha_apertura' => 'required|date',
            'fecha_cierre' => 'nullable|date',
            'saldo_inicial' => 'required|numeric',
            'depositos' => 'nullable|numeric',
            'salidas' => 'nullable|numeric',
            'ventas' => 'nullable|numeric',
            'ventas_contado' => 'nullable|numeric',
            'ventas_credito' => 'nullable|numeric',
            'pagos_efectivo' => 'nullable|numeric',
            'pagos_qr' => 'nullable|numeric',
            'pagos_transferencia' => 'nullable|numeric',
            'cuotas_ventas_credito' => 'nullable|numeric',
            'compras_contado' => 'nullable|numeric',
            'compras_credito' => 'nullable|numeric',
            'saldo_faltante' => 'nullable|numeric',
            'saldo_caja' => 'nullable|numeric',
            'estado' => 'boolean',
        ], [
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'fecha_apertura.required' => 'La fecha de apertura es obligatoria.',
            'fecha_apertura.date' => 'La fecha de apertura debe ser una fecha válida.',
            'fecha_cierre.date' => 'La fecha de cierre debe ser una fecha válida.',
            'saldo_inicial.required' => 'El saldo inicial es obligatorio.',
            'saldo_inicial.numeric' => 'El saldo inicial debe ser un número.',
            'depositos.numeric' => 'Los depósitos deben ser un número.',
            'salidas.numeric' => 'Las salidas deben ser un número.',
            'ventas.numeric' => 'Las ventas deben ser un número.',
            'ventas_contado.numeric' => 'Las ventas al contado deben ser un número.',
            'ventas_credito.numeric' => 'Las ventas a crédito deben ser un número.',
            'pagos_efectivo.numeric' => 'Los pagos en efectivo deben ser un número.',
            'pagos_qr.numeric' => 'Los pagos QR deben ser un número.',
            'pagos_transferencia.numeric' => 'Los pagos por transferencia deben ser un número.',
            'cuotas_ventas_credito.numeric' => 'Las cuotas de ventas a crédito deben ser un número.',
            'compras_contado.numeric' => 'Las compras al contado deben ser un número.',
            'compras_credito.numeric' => 'Las compras a crédito deben ser un número.',
            'saldo_faltante.numeric' => 'El saldo faltante debe ser un número.',
            'saldo_caja.numeric' => 'El saldo de caja debe ser un número.',
            'estado.boolean' => 'El estado debe ser verdadero o falso.',
        ]);

        $caja->update($request->all());

        return response()->json($caja);
    }

    /**
     * Calcula los totales de una caja basándose en ventas, compras y transacciones
     * 
     * @param int $cajaId ID de la caja
     * @return array Array con todos los cálculos
     */
    private function calcularTotalesCaja($cajaId)
    {
        // Obtener ventas de la caja
        $ventas = \App\Models\Venta::where('caja_id', $cajaId)
            ->with(['cliente', 'tipoVenta', 'tipoPago'])
            ->get();

        // Obtener compras de la caja (usando compras_base)
        $compras = \App\Models\CompraBase::where('caja_id', $cajaId)
            ->with(['proveedor'])
            ->get();

        // Obtener transacciones de caja
        $transacciones = \App\Models\TransaccionCaja::where('caja_id', $cajaId)->get();

        // Primero identificar ventas con pago QR (sin importar el tipo de venta)
        $ventasQR = $ventas->filter(function ($v) {
            $tipoPago = $v->tipoPago->nombre_tipo_pago ?? $v->tipoPago->nombre ?? '';
            return stripos($tipoPago, 'qr') !== false || stripos($tipoPago, 'qrcode') !== false;
        })->sum('total');

        // Calcular ventas al contado (solo las que NO se pagaron con QR)
        $ventasContado = $ventas->filter(function ($v) {
            $tipoVenta = $v->tipoVenta->nombre_tipo_ventas ?? $v->tipoVenta->nombre ?? '';
            $tipoPago = $v->tipoPago->nombre_tipo_pago ?? $v->tipoPago->nombre ?? '';
            $esContado = stripos($tipoVenta, 'contado') !== false || stripos($tipoVenta, 'efectivo') !== false;
            $noEsQR = stripos($tipoPago, 'qr') === false && stripos($tipoPago, 'qrcode') === false;
            return $esContado && $noEsQR;
        })->sum('total');

        // Calcular ventas a crédito (solo las que NO se pagaron con QR)
        $ventasCredito = $ventas->filter(function ($v) {
            $tipoVenta = $v->tipoVenta->nombre_tipo_ventas ?? $v->tipoVenta->nombre ?? '';
            $tipoPago = $v->tipoPago->nombre_tipo_pago ?? $v->tipoPago->nombre ?? '';
            $esCredito = stripos($tipoVenta, 'credito') !== false || stripos($tipoVenta, 'crédito') !== false;
            $noEsQR = stripos($tipoPago, 'qr') === false && stripos($tipoPago, 'qrcode') === false;
            return $esCredito && $noEsQR;
        })->sum('total');

        // Calcular compras al contado
        $comprasContado = $compras->where('tipo_compra', 'CONTADO')->sum('total') + 
                         $compras->where('tipo_compra', 'contado')->sum('total');

        // Calcular compras a crédito
        $comprasCredito = $compras->where('tipo_compra', 'CREDITO')->sum('total') + 
                         $compras->where('tipo_compra', 'credito')->sum('total') +
                         $compras->where('tipo_compra', 'CRÉDITO')->sum('total');

        // Calcular entradas (depositos/ingresos)
        $entradas = $transacciones->where('transaccion', 'ingreso')->sum('importe');

        // Calcular salidas (egresos)
        $salidas = $transacciones->where('transaccion', 'egreso')->sum('importe');

        // Total ventas
        $totalVentas = $ventas->sum('total');

        // Total compras
        $totalCompras = $compras->sum('total');

        return [
            'ventas_contado' => round($ventasContado, 2),
            'ventas_credito' => round($ventasCredito, 2),
            'ventas_qr' => round($ventasQR, 2),
            'compras_contado' => round($comprasContado, 2),
            'compras_credito' => round($comprasCredito, 2),
            'entradas' => round($entradas, 2),
            'salidas' => round($salidas, 2),
            'total_ventas' => round($totalVentas, 2),
            'total_compras' => round($totalCompras, 2)
        ];
    }

    /**
     * Get detailed calculations for a specific caja
     * Includes: ventas (contado, credito, qr), compras, transacciones, saldos
     */
    public function getCajaDetails($id)
    {
        $caja = Caja::with(['sucursal', 'user'])->find($id);

        if (!$caja) {
            return response()->json([
                'message' => 'Caja no encontrada',
                'error' => "No se encontró una caja con el ID: {$id}"
            ], 404);
        }

        // Calcular todos los totales en el backend
        $calculado = $this->calcularTotalesCaja($id);

        // Calcular saldo final
        $saldoInicial = (float) ($caja->saldo_inicial ?? 0);
        $saldoFaltante = (float) ($caja->saldo_faltante ?? 0);
        $saldoFinal = $saldoInicial + $calculado['total_ventas'] + $calculado['entradas'] - 
                     $calculado['total_compras'] - $calculado['salidas'] - $saldoFaltante;
        
        // Asegurar que el saldo no sea negativo (si es negativo, mostrar 0)
        $saldoFinal = max(0, round($saldoFinal, 2));
        $calculado['saldo_final'] = $saldoFinal;

        // Actualizar el saldo_caja en el objeto caja para que esté disponible en la respuesta
        $caja->saldo_caja = $saldoFinal;

        // Obtener ventas, compras y transacciones para mostrar en detalle
        $ventas = \App\Models\Venta::where('caja_id', $id)
            ->with(['cliente', 'tipoVenta', 'tipoPago'])
            ->get();

        $compras = \App\Models\CompraBase::where('caja_id', $id)
            ->with(['proveedor'])
            ->get();

        $transacciones = \App\Models\TransaccionCaja::where('caja_id', $id)->get();

        return response()->json([
            'caja' => $caja,
            'calculado' => $calculado,
            'ventas' => $ventas,
            'compras' => $compras,
            'transacciones' => $transacciones
        ]);
    }

    /**
     * Calcula los totales para todas las cajas (usado en listado)
     */
    public function calcularTotalesCajas(Request $request)
    {
        $cajas = Caja::with(['sucursal', 'user'])->get();
        
        $cajasConTotales = $cajas->map(function ($caja) {
            $calculado = $this->calcularTotalesCaja($caja->id);
            
            // Calcular saldo final
            $saldoInicial = (float) ($caja->saldo_inicial ?? 0);
            $saldoFaltante = (float) ($caja->saldo_faltante ?? 0);
            
            // Calcular saldo final: saldo inicial + ventas + entradas - compras - salidas - saldo faltante
            $saldoFinal = $saldoInicial + $calculado['total_ventas'] + $calculado['entradas'] - 
                         $calculado['total_compras'] - $calculado['salidas'] - $saldoFaltante;
            
            // Asegurar que el saldo no sea negativo (si es negativo, mostrar 0)
            $saldoFinal = max(0, round($saldoFinal, 2));
            
            return [
                'id' => $caja->id,
                'saldo_caja' => $saldoFinal,
                'ventas' => $calculado['total_ventas'], // Suma de TODAS las ventas (contado + crédito + QR)
                'compras' => $calculado['total_compras'],
                'depositos' => $calculado['entradas'],
                'salidas' => $calculado['salidas'],
                'ventas_contado' => $calculado['ventas_contado'],
                'ventas_credito' => $calculado['ventas_credito'],
                'ventas_qr' => $calculado['ventas_qr'], // Agregar ventas QR
                'compras_contado' => $calculado['compras_contado'],
                'compras_credito' => $calculado['compras_credito']
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $cajasConTotales
        ]);
    }

    public function destroy($id)
    {
        $caja = Caja::find($id);

        if (!$caja) {
            return response()->json([
                'message' => 'Caja no encontrada',
                'error' => "No se encontró una caja con el ID: {$id}"
            ], 404);
        }

        $caja->delete();
        return response()->json(null, 204);
    }
}
