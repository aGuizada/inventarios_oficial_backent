<?php

namespace App\Http\Requests\Venta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('estado') || $this->estado === null || $this->estado === '') {
            return;
        }

        $e = $this->estado;

        if (is_bool($e)) {
            $this->merge(['estado' => $e ? 'Activo' : 'Anulado']);

            return;
        }

        if ($e === 1 || $e === '1') {
            $this->merge(['estado' => 'Activo']);

            return;
        }

        if ($e === 0 || $e === '0') {
            $this->merge(['estado' => 'Anulado']);
        }
    }

    protected function tienePagosMixtos(): bool
    {
        return $this->has('pagos') && is_array($this->pagos) && count($this->pagos) > 0;
    }

    public function rules(): array
    {
        $tienePagosMixtos = $this->tienePagosMixtos();

        return [
            'cliente_id' => 'required|exists:clientes,id',
            'tipo_venta_id' => 'required|exists:tipo_ventas,id',
            'tipo_pago_id' => $tienePagosMixtos ? 'nullable|exists:tipo_pagos,id' : 'required|exists:tipo_pagos,id',
            'tipo_comprobante' => 'nullable|string|max:50',
            'serie_comprobante' => 'nullable|string|max:50',
            'num_comprobante' => 'nullable|string|max:50',
            'fecha_hora' => 'required|date',
            'estado' => ['nullable', 'string', 'max:20', Rule::in(['Activo', 'Anulado', 'Cancelada'])],
            'caja_id' => 'nullable|exists:cajas,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.unidad_medida' => 'nullable|string|in:Unidad,Paquete,Centimetro,Metro',
            'pagos' => 'nullable|array',
            'pagos.*.tipo_pago_id' => 'required|exists:tipo_pagos,id',
            'pagos.*.monto' => 'required|numeric|min:0',
            'numero_cuotas' => 'nullable|integer|min:1',
            'tiempo_dias_cuota' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'tipo_venta_id.required' => 'El tipo de venta es obligatorio.',
            'tipo_venta_id.exists' => 'El tipo de venta seleccionado no existe.',
            'tipo_pago_id.required' => 'El tipo de pago es obligatorio.',
            'tipo_pago_id.exists' => 'El tipo de pago seleccionado no existe.',
            'tipo_comprobante.string' => 'El tipo de comprobante debe ser una cadena de texto.',
            'tipo_comprobante.max' => 'El tipo de comprobante no puede tener más de 50 caracteres.',
            'serie_comprobante.string' => 'La serie del comprobante debe ser una cadena de texto.',
            'serie_comprobante.max' => 'La serie del comprobante no puede tener más de 50 caracteres.',
            'num_comprobante.string' => 'El número de comprobante debe ser una cadena de texto.',
            'num_comprobante.max' => 'El número de comprobante no puede tener más de 50 caracteres.',
            'fecha_hora.required' => 'La fecha y hora son obligatorias.',
            'fecha_hora.date' => 'La fecha y hora deben ser una fecha válida.',
            'estado.in' => 'El estado debe ser Activo, Anulado o Cancelada.',
            'caja_id.exists' => 'La caja seleccionada no existe.',
            'detalles.required' => 'Los detalles de la venta son obligatorios.',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.*.articulo_id.required' => 'El artículo es obligatorio en los detalles.',
            'detalles.*.articulo_id.exists' => 'El artículo seleccionado no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en los detalles.',
            'detalles.*.cantidad.numeric' => 'La cantidad debe ser un número válido.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 0.01.',
            'detalles.*.precio.required' => 'El precio es obligatorio en los detalles.',
            'detalles.*.precio.numeric' => 'El precio debe ser un número.',
            'detalles.*.descuento.numeric' => 'El descuento debe ser un número.',
            'pagos.array' => 'Los pagos deben ser un arreglo.',
            'pagos.*.tipo_pago_id.required' => 'El tipo de pago es obligatorio en los pagos.',
            'pagos.*.tipo_pago_id.exists' => 'El tipo de pago seleccionado no existe en los pagos.',
            'pagos.*.monto.required' => 'El monto es obligatorio en los pagos.',
            'pagos.*.monto.numeric' => 'El monto debe ser un número.',
            'pagos.*.monto.min' => 'El monto debe ser al menos 0.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $tiene = $this->tienePagosMixtos();
            if (! $tiene && (! $this->tipo_pago_id || $this->tipo_pago_id === '')) {
                $v->errors()->add('tipo_pago_id', 'El tipo de pago es obligatorio.');
            }
            if ($tiene && count($this->pagos ?? []) === 0) {
                $v->errors()->add('pagos', 'Debe agregar al menos un pago.');
            }
        });
    }
}
