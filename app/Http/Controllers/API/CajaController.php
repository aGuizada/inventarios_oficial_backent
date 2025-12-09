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
        return $this->paginateResponse($query, $request, 15, 100);
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
