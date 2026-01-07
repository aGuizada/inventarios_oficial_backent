<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class InventarioTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            [
                'codigo_articulo' => '',
                'nombre_articulo' => '',
                'marca' => '',
                'sucursal' => '',
                'almacen' => '',
                'saldo_stock' => '',
                'cantidad' => '',
                'fecha_vencimiento' => '',
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'codigo_articulo',
            'nombre_articulo',
            'marca',
            'sucursal',
            'almacen',
            'saldo_stock',
            'cantidad',
            'fecha_vencimiento',
        ];
    }

    public function title(): string
    {
        return 'Plantilla Inventario';
    }
}
