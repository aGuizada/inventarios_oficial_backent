<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class KardexExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $kardexMovimientos;
    protected $tipo;

    public function __construct($kardexMovimientos, $tipo = 'fisico')
    {
        $this->kardexMovimientos = $kardexMovimientos;
        $this->tipo = $tipo;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        if ($this->tipo === 'valorado') {
            return $this->kardexMovimientos->map(function ($mov) {
                return [
                    'fecha' => $mov->fecha,
                    'tipo' => $mov->tipo_movimiento,
                    'documento' => $mov->documento_numero,
                    'articulo' => $mov->articulo->nombre ?? 'N/A',
                    'almacen' => $mov->almacen->nombre_almacen ?? 'N/A',
                    'entrada' => $mov->cantidad_entrada,
                    'salida' => $mov->cantidad_salida,
                    'saldo' => $mov->cantidad_saldo,
                    'costo_unit' => $mov->costo_unitario,
                    'costo_total' => $mov->costo_total,
                    'precio_unit' => $mov->precio_unitario ?? 0,
                    'precio_total' => $mov->precio_total ?? 0,
                ];
            });
        }

        // Físico
        return $this->kardexMovimientos->map(function ($mov) {
            return [
                'fecha' => $mov->fecha,
                'tipo' => $mov->tipo_movimiento,
                'documento' => $mov->documento_numero,
                'articulo' => $mov->articulo->nombre ?? 'N/A',
                'almacen' => $mov->almacen->nombre_almacen ?? 'N/A',
                'entrada' => $mov->cantidad_entrada,
                'salida' => $mov->cantidad_salida,
                'saldo' => $mov->cantidad_saldo,
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        if ($this->tipo === 'valorado') {
            return [
                'Fecha',
                'Tipo Movimiento',
                'Documento',
                'Artículo',
                'Almacén',
                'Entrada',
                'Salida',
                'Saldo',
                'Costo Unit.',
                'Costo Total',
                'Precio Unit.',
                'Precio Total',
            ];
        }

        return [
            'Fecha',
            'Tipo Movimiento',
            'Documento',
            'Artículo',
            'Almacén',
            'Entrada',
            'Salida',
            'Saldo',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para los encabezados
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '3B82F6']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        if ($this->tipo === 'valorado') {
            return [
                'A' => 12,
                'B' => 18,
                'C' => 15,
                'D' => 30,
                'E' => 20,
                'F' => 12,
                'G' => 12,
                'H' => 12,
                'I' => 12,
                'J' => 12,
                'K' => 12,
                'L' => 12,
            ];
        }

        return [
            'A' => 12,
            'B' => 18,
            'C' => 15,
            'D' => 30,
            'E' => 20,
            'F' => 12,
            'G' => 12,
            'H' => 12,
        ];
    }
}
