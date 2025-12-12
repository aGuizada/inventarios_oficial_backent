<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasReporteExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $ventas;
    protected $resumen;

    public function __construct($ventas, $resumen)
    {
        $this->ventas = $ventas;
        $this->resumen = $resumen;
    }

    public function collection()
    {
        return $this->ventas->map(function ($venta) {
            return [
                'fecha' => $venta->fecha_hora,
                'folio' => $venta->folio ?? 'N/A',
                'cliente' => $venta->cliente->nombre ?? 'Cliente General',
                'tipo_venta' => $venta->tipoVenta->nombre_tipo_venta ?? 'N/A',
                'tipo_pago' => $venta->tipoPago->nombre_tipo_pago ?? 'N/A',
                'subtotal' => $venta->subtotal,
                'descuento' => $venta->descuento ?? 0,
                'total' => $venta->total,
                'vendedor' => $venta->usuario->name ?? 'N/A',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Folio',
            'Cliente',
            'Tipo Venta',
            'Tipo Pago',
            'Subtotal',
            'Descuento',
            'Total',
            'Vendedor',
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
            'A' => 18,
            'B' => 12,
            'C' => 30,
            'D' => 15,
            'E' => 15,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 20,
        ];
    }

    public function title(): string
    {
        return 'Reporte de Ventas';
    }
}
