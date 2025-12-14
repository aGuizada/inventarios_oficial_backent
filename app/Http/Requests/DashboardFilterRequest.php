<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request para validar filtros de fecha en el Dashboard
 * Soporta diferentes modos de filtrado: rango, mes/año, día específico
 */
class DashboardFilterRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para los filtros de fecha.
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'day' => 'nullable|integer|min:1|max:31',
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'year.integer' => 'El año debe ser un número entero.',
            'year.min' => 'El año debe ser al menos 2000.',
            'year.max' => 'El año no puede ser mayor a 2100.',
            'month.integer' => 'El mes debe ser un número entero.',
            'month.min' => 'El mes debe estar entre 1 y 12.',
            'month.max' => 'El mes debe estar entre 1 y 12.',
            'day.integer' => 'El día debe ser un número entero.',
            'day.min' => 'El día debe estar entre 1 y 31.',
            'day.max' => 'El día debe estar entre 1 y 31.',
            'sucursal_id.integer' => 'El ID de sucursal debe ser un número entero.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Manejo de errores de validación.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Validación adicional después de las reglas básicas.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si se proporciona mes, también debe proporcionarse año
            if ($this->has('month') && !$this->has('year')) {
                $validator->errors()->add('year', 'Debe proporcionar el año cuando especifica el mes.');
            }

            // Si se proporciona día, también debe proporcionarse mes y año
            if ($this->has('day') && (!$this->has('month') || !$this->has('year'))) {
                $validator->errors()->add('month', 'Debe proporcionar mes y año cuando especifica el día.');
            }
        });
    }
}
