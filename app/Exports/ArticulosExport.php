<?php

namespace App\Exports;

use App\Models\Articulo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArticulosExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function collection()
    {
        return Articulo::with(['categoria', 'marca', 'medida', 'industria'])
            ->get()
            ->map(function ($articulo) {
                return [
                    'codigo' => $articulo->codigo,
                    'nombre' => $articulo->nombre,
                    'categoria' => $articulo->categoria->nombre ?? 'N/A',
                    'marca' => $articulo->marca->nombre_marca ?? 'N/A',
                    'medida' => $articulo->medida->nombre_medida ?? 'N/A',
                    'precio_venta' => $articulo->precio_venta_unid,
                    'precio_costo' => $articulo->precio_costo_unid,
                    'gravamen' => $articulo->gravamen ? 'Sí' : 'No',
                    'estado' => $articulo->estado ? 'Activo' : 'Inactivo',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombre',
            'Categoría',
            'Marca',
            'Medida',
            'Precio Venta',
            'Precio Costo',
            'Gravamen',
            'Estado',
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
            'A' => 15,
            'B' => 35,
            'C' => 20,
            'D' => 20,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 12,
            'I' => 12,
        ];
    }

    public function title(): string
    {
        return 'Artículos';
    }
}
