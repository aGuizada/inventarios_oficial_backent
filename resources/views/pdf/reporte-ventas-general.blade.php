<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General de Ventas por Fechas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #3B82F6;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #1f2937;
        }
        .resumen {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .resumen-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .resumen-item .label {
            font-size: 9px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .resumen-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .fecha-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .fecha-header {
            background: #3B82F6;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fecha-header h2 {
            margin: 0;
            font-size: 14px;
        }
        .fecha-resumen {
            background: #fef3c7;
            padding: 8px;
            border-radius: 3px;
            margin-bottom: 10px;
            font-size: 9px;
            display: flex;
            justify-content: space-around;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th {
            background-color: #6b7280;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 8px;
        }
        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-fecha {
            background-color: #dbeafe;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte General de Ventas por Fechas</h1>
    </div>

    <div class="resumen">
        <div class="resumen-grid">
            <div class="resumen-item">
                <div class="label">Total General de Ventas</div>
                <div class="value">Bs. {{ number_format($resumen['total_ventas'], 2) }}</div>
            </div>
            <div class="resumen-item">
                <div class="label">Cantidad Total de Ventas</div>
                <div class="value">{{ $resumen['cantidad_ventas'] }}</div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; font-size: 9px;">
            <strong>Período:</strong> 
            {{ $resumen['fecha_desde'] ? date('d/m/Y', strtotime($resumen['fecha_desde'])) : 'Todos' }} 
            a 
            {{ $resumen['fecha_hasta'] ? date('d/m/Y', strtotime($resumen['fecha_hasta'])) : 'Todos' }}
        </div>
    </div>

    @foreach($ventas_por_fecha as $fecha => $datos)
        <div class="fecha-section">
            <div class="fecha-header">
                <h2>{{ date('d/m/Y', strtotime($fecha)) }}</h2>
                <div style="font-size: 12px;">
                    <strong>{{ $datos['cantidad'] }} venta(s) | Total: Bs. {{ number_format($datos['total'], 2) }}</strong>
                </div>
            </div>

            <div class="fecha-resumen">
                <div><strong>Cantidad:</strong> {{ $datos['cantidad'] }} ventas</div>
                <div><strong>Total del Día:</strong> Bs. {{ number_format($datos['total'], 2) }}</div>
                <div><strong>Promedio por Venta:</strong> Bs. {{ number_format($datos['total'] / max($datos['cantidad'], 1), 2) }}</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th># Venta</th>
                        <th>Comprobante</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Tipo Venta</th>
                        <th>Tipo Pago</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($datos['ventas'] as $venta)
                        <tr>
                            <td class="text-center">{{ date('H:i', strtotime($venta->fecha_hora)) }}</td>
                            <td class="text-center">{{ $venta->id }}</td>
                            <td>{{ $venta->num_comprobante ?? 'N/A' }}</td>
                            <td>{{ $venta->cliente->nombre ?? 'Cliente General' }}</td>
                            <td>{{ $venta->user->name ?? 'N/A' }}</td>
                            <td>{{ $venta->tipoVenta->nombre_tipo_ventas ?? 'N/A' }}</td>
                            <td>{{ $venta->tipoPago->nombre_tipo_pago ?? 'N/A' }}</td>
                            <td class="text-right"><strong>Bs. {{ number_format($venta->total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                    <tr class="total-fecha">
                        <td colspan="7" class="text-right"><strong>Total del Día</strong></td>
                        <td class="text-right"><strong>Bs. {{ number_format($datos['total'], 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>

