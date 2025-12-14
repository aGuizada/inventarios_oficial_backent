<?php

namespace App\Imports;

use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\Almacen;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class InventarioImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $importedCount = 0;

    public function model(array $row)
    {
        $this->importedCount++;

        // Buscar o crear artículo y almacén
        $articulo = Articulo::firstOrCreate([
            'codigo' => $row['codigo_articulo'] ?? null
        ], [
            'nombre' => $row['nombre_articulo'] ?? 'Sin nombre'
        ]);
        // Buscar sucursal: si viene en el Excel, buscar por nombre, si no, tomar la de menor id
        $sucursalNombre = $row['sucursal'] ?? null;
        $sucursal = null;
        if ($sucursalNombre) {
            $sucursal = \App\Models\Sucursal::where('nombre', $sucursalNombre)->first();
        }
        if (!$sucursal) {
            $sucursal = \App\Models\Sucursal::orderBy('id')->first();
        }

        $almacen = Almacen::firstOrCreate([
            'nombre_almacen' => $row['almacen'] ?? 'General',
            'sucursal_id' => $sucursal?->id,
        ]);

        $inventario = Inventario::updateOrCreate(
            [
                'articulo_id' => $articulo->id,
                'almacen_id' => $almacen->id,
                'fecha_vencimiento' => $row['fecha_vencimiento'] ?? '2099-01-01'
            ],
            [
                'saldo_stock' => $row['saldo_stock'] ?? 0,
                'cantidad' => $row['cantidad'] ?? 0,
            ]
        );

        return $inventario;
    }

    public function rules(): array
    {
        return [
            'codigo_articulo' => ['required', 'string'],
            'nombre_articulo' => ['required', 'string'],
            'sucursal' => ['nullable', 'string', 'exists:sucursales,nombre'],
            'almacen' => ['required', 'string'],
            'saldo_stock' => ['required', 'integer'],
            'cantidad' => ['required', 'integer'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'codigo_articulo.required' => 'El código del artículo es obligatorio.',
            'nombre_articulo.required' => 'El nombre del artículo es obligatorio.',
            'sucursal.exists' => 'La sucursal especificada no existe.',
            'almacen.required' => 'El almacén es obligatorio.',
            'saldo_stock.required' => 'El saldo de stock es obligatorio.',
            'cantidad.required' => 'La cantidad es obligatoria.'
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return count($this->failures());
    }
}
