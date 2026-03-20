<?php

namespace App\Http\Requests\Traspaso;

use App\Models\Traspaso;
use Illuminate\Foundation\Http\FormRequest;

class StoreTraspasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Traspaso::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo_traspaso' => 'required|string|max:20|unique:traspasos,codigo_traspaso',
            'sucursal_origen_id' => 'required|exists:sucursales,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id',
            'almacen_origen_id' => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id',
            'fecha_solicitud' => 'required|date',
            'fecha_aprobacion' => 'nullable|date',
            'fecha_entrega' => 'nullable|date',
            'tipo_traspaso' => 'nullable|string|max:50',
            'estado' => 'nullable|string|max:50',
            'motivo' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'usuario_aprobador_id' => 'nullable|exists:users,id',
            'usuario_receptor_id' => 'nullable|exists:users,id',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.inventario_id' => 'required|exists:inventarios,id',
            'detalles.*.cantidad_solicitada' => 'required|integer|min:1',
            'detalles.*.precio_costo' => 'nullable|numeric',
            'detalles.*.precio_venta' => 'nullable|numeric',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_traspaso.required' => 'El código del traspaso es obligatorio.',
            'codigo_traspaso.max' => 'El código del traspaso no puede superar 20 caracteres.',
            'codigo_traspaso.unique' => 'Ya existe un traspaso con ese código.',
            'sucursal_origen_id.required' => 'La sucursal de origen es obligatoria.',
            'sucursal_destino_id.required' => 'La sucursal de destino es obligatoria.',
            'almacen_origen_id.required' => 'El almacén de origen es obligatorio.',
            'almacen_destino_id.required' => 'El almacén de destino es obligatorio.',
            'fecha_solicitud.required' => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
