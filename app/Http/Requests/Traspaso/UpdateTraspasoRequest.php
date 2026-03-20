<?php

namespace App\Http\Requests\Traspaso;

use App\Models\Traspaso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTraspasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $traspaso = $this->resolvedTraspaso();

        return $this->user() !== null && $this->user()->can('update', $traspaso);
    }

    private function resolvedTraspaso(): Traspaso
    {
        $param = $this->route('traspaso');
        if ($param instanceof Traspaso) {
            return $param;
        }

        return Traspaso::findOrFail($param);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $traspaso = $this->resolvedTraspaso();

        return [
            'codigo_traspaso' => [
                'required',
                'string',
                'max:20',
                Rule::unique('traspasos', 'codigo_traspaso')->ignore($traspaso->id),
            ],
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
            'fecha_solicitud.required' => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
