<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ProveedoresExport implements FromCollection, WithHeadings
{
    /**
     * Retorna una colección con una fila de ejemplo para la plantilla
     */
    public function collection()
    {
        // Retornamos una fila de ejemplo para que el usuario vea el formato
        return collect([
            [
                'Proveedor Ejemplo S.A.',
                '591-2345678',
                'proveedor@ejemplo.com',
                '1234567890',
                'Av. Principal #123, La Paz',
                'Mayorista',
                1,
            ]
        ]);
    }

    /**
     * Define los encabezados del Excel
     */
    public function headings(): array
    {
        return [
            'nombre',
            'telefono',
            'email',
            'nit',
            'direccion',
            'tipo_proveedor',
            'estado',
        ];
    }
}
