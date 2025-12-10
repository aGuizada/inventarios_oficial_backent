<?php

namespace App\Imports;

use App\Models\Proveedor;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ProveedoresImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $importedCount = 0;

    /**
     * Crea un modelo Proveedor por cada fila del Excel
     *
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->importedCount++;

        return new Proveedor([
            'nombre' => $row['nombre'],
            'telefono' => $row['telefono'] ?? null,
            'email' => $row['email'] ?? null,
            'nit' => $row['nit'] ?? null,
            'direccion' => $row['direccion'] ?? null,
            'tipo_proveedor' => $row['tipo_proveedor'] ?? null,
            'estado' => $row['estado'] ?? 1,
        ]);
    }

    /**
     * Prepara los datos antes de la validación
     * Convierte campos numéricos a string
     */
    public function prepareForValidation($data, $index)
    {
        // Convertir campos que deben ser string
        if (isset($data['telefono'])) {
            $data['telefono'] = (string) $data['telefono'];
        }
        if (isset($data['nit'])) {
            $data['nit'] = (string) $data['nit'];
        }
        if (isset($data['email'])) {
            $data['email'] = (string) $data['email'];
        }
        if (isset($data['direccion'])) {
            $data['direccion'] = (string) $data['direccion'];
        }
        if (isset($data['tipo_proveedor'])) {
            $data['tipo_proveedor'] = (string) $data['tipo_proveedor'];
        }

        return $data;
    }

    /**
     * Reglas de validación para cada fila
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'tipo_proveedor' => 'nullable|string|max:50',
            'estado' => 'required|boolean',
        ];
    }

    /**
     * Mensajes de validación personalizados
     *
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'email.email' => 'El formato del email no es válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'nit.max' => 'El NIT no puede exceder 20 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',
            'tipo_proveedor.max' => 'El tipo de proveedor no puede exceder 50 caracteres.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.boolean' => 'El estado debe ser 0 o 1.',
        ];
    }

    /**
     * Retorna el número de proveedores importados exitosamente
     *
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Retorna el número de filas omitidas
     *
     * @return int
     */
    public function getSkippedCount(): int
    {
        return count($this->failures());
    }
}
