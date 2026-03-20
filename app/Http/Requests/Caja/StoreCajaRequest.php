<?php

namespace App\Http\Requests\Caja;

use App\Models\Caja;
use Illuminate\Foundation\Http\FormRequest;

class StoreCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Caja::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => 'required|exists:sucursales,id',
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
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
        ];
    }
}
