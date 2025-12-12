<?php

namespace App\Exports;

use App\Models\Inventario;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventariosExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function collection()
    {
        return Inventario::with(['articulo', 'almacen'])
            ->get()
            ->map(function ($inv) {
                return [
                    'articulo' => $inv->articulo->nombre ?? 'N/A',
                    'codigo' => $inv->articulo->codigo ?? 'N/A',
                    'almacen' => $inv->almacen->nombre_almacen ?? 'N/A',
                    'stock' => $inv->saldo_stock,
                    'costo_unitario' => $inv->articulo->precio_costo_unid ?? 0,
                    'valor_total' => $inv->saldo_stock * ($inv->articulo->precio_costo_unid ?? 0),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Artículo',
            'Código',
            'Almacén',
            'Stock Actual',
            'Costo Unitario',
            'Valor Total',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '3B82F6']
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35,
            'B' => 15,
            'C' => 25,
            'D' => 15,
            'E' => 15,
            'F' => 15,
        ];
    }

    public function title(): string
    {
        return 'Inventario';
    }
}
