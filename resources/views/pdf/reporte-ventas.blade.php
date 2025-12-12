<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .periodo {
            text-align: center;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .resumen {
            margin-bottom: 20px;
            background: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
        }

        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .resumen-item {
            text-align: center;
        }

        .resumen-item .label {
            font-size: 9px;
            color: #6b7280;
        }

        .resumen-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #3B82F6;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9px;
        }

        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 9px;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Reporte de Ventas</h1>
    </div>

    <div class="periodo">
        <strong>Período:</strong> {{ $periodo['fecha_desde'] }} a {{ $periodo['fecha_hasta'] }}
    </div>

    <div class="resumen">
        <div class="resumen-grid">
            <div class="resumen-item">
                <div class="label">Total Ventas</div>
                <div class="value">Bs. {{ number_format($resumen['total_ventas'], 2) }}</div>
            </div>
            <div class="resumen-item">
                <div class="label">Transacciones</div>
                <div class="value">{{ $resumen['cantidad_transacciones'] }}</div>
            </div>
            <div class="resumen-item">
                <div class="label">Ticket Promedio</div>
                <div class="value">Bs. {{ number_format($resumen['ticket_promedio'], 2) }}</div>
            </div>
        </div>
    </div>

    <h3>Detalle de Ventas</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Tipo Venta</th>
                <th>Tipo Pago</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Descuento</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventas as $venta)
                <tr>
                    <td>{{ date('d/m/Y H:i', strtotime($venta->fecha_hora)) }}</td>
                    <td>{{ $venta->folio ?? 'N/A' }}</td>
                    <td>{{ $venta->cliente->nombre ?? 'Cliente General' }}</td>
                    <td>{{ $venta->tipoVenta->nombre_tipo_venta ?? 'N/A' }}</td>
                    <td>{{ $venta->tipoPago->nombre_tipo_pago ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($venta->subtotal, 2) }}</td>
                    <td class="text-right">{{ number_format($venta->descuento ?? 0, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($venta->total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($metodos_pago))
        <h3>Métodos de Pago</h3>
        <table style="width: 50%;">
            <thead>
                <tr>
                    <th>Método</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($metodos_pago as $metodo)
                    <tr>
                        <td>{{ $metodo['tipo_pago'] }}</td>
                        <td class="text-right">{{ $metodo['cantidad'] }}</td>
                        <td class="text-right">Bs. {{ number_format($metodo['total'], 2) }}</td>
                        <td class="text-right">{{ number_format($metodo['porcentaje'], 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>

</html>