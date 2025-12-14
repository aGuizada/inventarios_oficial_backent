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
        return Articulo::with(['categoria', 'proveedor', 'marca', 'medida', 'industria'])
            ->get()
            ->map(function ($articulo) {
                return [
                    'codigo' => $articulo->codigo,
                    'nombre' => $articulo->nombre,
                    'categoria' => optional($articulo->categoria)->nombre ?? 'N/A',
                    'proveedor' => optional($articulo->proveedor)->nombre ?? 'N/A',
                    'marca' => optional($articulo->marca)->nombre
                        ?? optional($articulo->marca)->getAttribute('nombre_marca')
                        ?? 'N/A',
                    'medida' => optional($articulo->medida)->nombre_medida ?? 'N/A',
                    'industria' => optional($articulo->industria)->nombre ?? 'N/A',
                    'unidad_envase' => $articulo->unidad_envase,
                    'precio_costo_unid' => $articulo->precio_costo_unid,
                    'precio_costo_paq' => $articulo->precio_costo_paq,
                    'precio_venta' => $articulo->precio_venta,
                    'precio_uno' => $articulo->precio_uno,
                    'precio_dos' => $articulo->precio_dos,
                    'precio_tres' => $articulo->precio_tres,
                    'precio_cuatro' => $articulo->precio_cuatro,
                    'stock' => $articulo->stock,
                    'descripcion' => $articulo->descripcion,
                    'costo_compra' => $articulo->costo_compra,
                    'vencimiento' => $articulo->vencimiento,
        
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
            'Proveedor',
            'Marca',
            'Medida',
            'Industria',
            'Unidad Envase',
            'Precio Costo Unid',
            'Precio Costo Paq',
            'Precio Venta',
            'Precio Uno',
            'Precio Dos',
            'Precio Tres',
            'Precio Cuatro',
            'Stock',
            'Descripcion',
            'Costo Compra',
            'Vencimiento',

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
            'D' => 25,
            'E' => 20,
            'F' => 15,
            'G' => 20,
            'H' => 15,
            'I' => 18,
            'J' => 18,
            'K' => 18,
            'L' => 15,
            'M' => 15,
            'N' => 15,
            'O' => 15,
            'P' => 12,
            'Q' => 35,
            'R' => 18,
            'S' => 15,
            'T' => 12,
            'U' => 12,
        ];
    }

    public function title(): string
    {
        return 'Artículos';
    }
}
