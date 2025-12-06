<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ArticulosExport implements FromCollection, WithHeadings
{
    /**
     * Retorna una colección con una fila de ejemplo para la plantilla
     */
    public function collection()
    {
        // Retornamos una fila de ejemplo para que el usuario vea el formato
        return collect([
            [
                'ART-001',
                'Artículo de Ejemplo',
                1,
                1,
                1,
                1,
                1,
                12,
                10.50,
                120.00,
                15.00,
                14.50,
                14.00,
                13.50,
                13.00,
                100,
                'Descripción del artículo',
                10.00,
                365,
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
            'codigo',
            'nombre',
            'categoria_id',
            'proveedor_id',
            'medida_id',
            'marca_id',
            'industria_id',
            'unidad_envase',
            'precio_costo_unid',
            'precio_costo_paq',
            'precio_venta',
            'precio_uno',
            'precio_dos',
            'precio_tres',
            'precio_cuatro',
            'stock',
            'descripcion',
            'costo_compra',
            'vencimiento',
            'estado',
        ];
    }
}
