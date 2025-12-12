<?php

namespace App\Exports;

use App\Models\Cliente;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function collection()
    {
        return Cliente::all()
            ->map(function ($cliente) {
                return [
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento ?? 'N/A',
                    'telefono' => $cliente->telefono ?? 'N/A',
                    'email' => $cliente->email ?? 'N/A',
                    'direccion' => $cliente->direccion ?? 'N/A',
                    'tipo_cliente' => $cliente->tipo_cliente ?? 'General',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Documento',
            'Teléfono',
            'Email',
            'Dirección',
            'Tipo Cliente',
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
            'A' => 30,
            'B' => 20,
            'C' => 20,
            'D' => 30,
            'E' => 40,
            'F' => 20,
        ];
    }

    public function title(): string
    {
        return 'Clientes';
    }
}
