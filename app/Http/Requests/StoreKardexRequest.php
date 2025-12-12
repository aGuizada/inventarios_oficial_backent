<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKardexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'fecha' => 'required|date|before_or_equal:today',
            'tipo_movimiento' => 'required|in:ajuste,compra,venta,traspaso_entrada,traspaso_salida,devolucion',
            'articulo_id' => 'required|exists:articulos,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'cantidad_entrada' => 'nullable|numeric|min:0|required_without:cantidad_salida',
            'cantidad_salida' => 'nullable|numeric|min:0|required_without:cantidad_entrada',
            'costo_unitario' => 'required|numeric|min:0',
            'precio_unitario' => 'nullable|numeric|min:0',
            'documento_tipo' => 'nullable|string|max:50',
            'documento_numero' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:500',
            'motivo' => 'nullable|string|max:255',
            'compra_id' => 'nullable|exists:compras_base,id',
            'venta_id' => 'nullable|exists:ventas,id',
            'traspaso_id' => 'nullable|exists:traspasos,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria',
            'fecha.date' => 'La fecha debe ser válida',
            'fecha.before_or_equal' => 'La fecha no puede ser futura',
            'tipo_movimiento.required' => 'El tipo de movimiento es obligatorio',
            'tipo_movimiento.in' => 'El tipo de movimiento no es válido',
            'articulo_id.required' => 'Debe seleccionar un artículo',
            'articulo_id.exists' => 'El artículo seleccionado no existe',
            'almacen_id.required' => 'Debe seleccionar un almacén',
            'almacen_id.exists' => 'El almacén seleccionado no existe',
            'cantidad_entrada.required_without' => 'Debe especificar entrada o salida',
            'cantidad_salida.required_without' => 'Debe especificar entrada o salida',
            'cantidad_entrada.min' => 'La cantidad de entrada debe ser positiva',
            'cantidad_salida.min' => 'La cantidad de salida debe ser positiva',
            'costo_unitario.required' => 'El costo unitario es obligatorio',
            'costo_unitario.min' => 'El costo unitario debe ser positivo',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores vacíos a null
        if ($this->cantidad_entrada === '') {
            $this->merge(['cantidad_entrada' => null]);
        }
        if ($this->cantidad_salida === '') {
            $this->merge(['cantidad_salida' => null]);
        }
    }
}
