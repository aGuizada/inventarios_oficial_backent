<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterKardexRequest extends FormRequest
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
            'articulo_id' => 'nullable|exists:articulos,id',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'tipo_movimiento' => 'nullable|in:ajuste,compra,venta,traspaso_entrada,traspaso_salida,devolucion',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,fecha,tipo_movimiento,cantidad_entrada,cantidad_salida',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'articulo_id.exists' => 'El artículo seleccionado no existe',
            'almacen_id.exists' => 'El almacén seleccionado no existe',
            'tipo_movimiento.in' => 'El tipo de movimiento no es válido',
            'fecha_desde.date' => 'La fecha desde debe ser válida',
            'fecha_hasta.date' => 'La fecha hasta debe ser válida',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde',
            'per_page.max' => 'El máximo de registros por página es 100',
        ];
    }
}
