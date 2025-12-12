<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsuariosExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function collection()
    {
        return User::with('rol')
            ->get()
            ->map(function ($user) {
                return [
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol->nombre ?? 'N/A',
                    'estado' => $user->estado ? 'Activo' : 'Inactivo',
                    'fecha_creacion' => $user->created_at->format('d/m/Y'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Email',
            'Rol',
            'Estado',
            'Fecha Creación',
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
            'B' => 35,
            'C' => 20,
            'D' => 15,
            'E' => 20,
        ];
    }

    public function title(): string
    {
        return 'Usuarios';
    }
}
