<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Utilidades por Sucursal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 20px 35px;
            padding: 0;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }

        .periodo {
            text-align: center;
            margin-bottom: 8px;
            font-size: 10px;
            color: #000;
        }

        .resumen {
            margin-bottom: 10px;
            border: 1px solid #000;
            padding: 6px;
        }

        .resumen-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .resumen-item {
            display: table-cell;
            text-align: center;
            border-right: 1px solid #000;
            padding: 4px;
            vertical-align: middle;
        }

        .resumen-item:last-child {
            border-right: none;
        }

        .resumen-item .label {
            font-size: 9px;
            margin-bottom: 2px;
            color: #000;
        }

        .resumen-item .value {
            font-size: 11px;
            font-weight: bold;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8px;
        }

        th {
            background-color: #f5f5f5;
            border: 1px solid #000;
            padding: 5px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            color: #000;
        }

        th.text-right {
            text-align: right;
        }

        th.text-center {
            text-align: center;
        }

        td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8px;
            color: #000;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 9px;
            border-top: 1px solid #000;
            padding-top: 5px;
            color: #000;
        }

        h3 {
            font-size: 11px;
            margin-bottom: 5px;
            color: #000;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Reporte de Utilidades por Sucursal</h1>
    </div>

    <div class="periodo">
        <strong>Período:</strong> {{ $fecha_desde }} a {{ $fecha_hasta }}
    </div>

    @if(isset($sucursal_filtro) && $sucursal_filtro)
        <div class="sucursal-filtro" style="text-align: center; margin-bottom: 8px; font-size: 10px; font-weight: bold; color: #000;">
            <strong>Sucursal:</strong> {{ $sucursal_filtro }}
        </div>
    @endif

    @if(isset($resumen_general))
        <div class="resumen">
            <div class="resumen-grid">
                <div class="resumen-item">
                    <div class="label">Total Ventas</div>
                    <div class="value">Bs. {{ number_format($resumen_general['total_ventas'], 2) }}</div>
                </div>
                <div class="resumen-item">
                    <div class="label">Total Compras</div>
                    <div class="value">Bs. {{ number_format($resumen_general['total_compras'], 2) }}</div>
                </div>
                <div class="resumen-item">
                    <div class="label">Utilidad Total</div>
                    <div class="value">Bs. {{ number_format($resumen_general['utilidad_total'], 2) }}</div>
                </div>
                <div class="resumen-item">
                    <div class="label">Margen Promedio</div>
                    <div class="value">{{ number_format($resumen_general['margen_promedio'], 2) }}%</div>
                </div>
            </div>
        </div>
    @endif

    <h3 style="font-size: 11px; margin-bottom: 8px;">Detalle por Sucursal</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Sucursal</th>
                <th class="text-right" style="width: 15%;">Total Ventas</th>
                <th class="text-right" style="width: 15%;">Total Compras</th>
                <th class="text-right" style="width: 15%;">Utilidad</th>
                <th class="text-right" style="width: 12%;">Margen %</th>
                <th class="text-center" style="width: 11%;"># Ventas</th>
                <th class="text-center" style="width: 12%;"># Compras</th>
            </tr>
        </thead>
        <tbody>
            @foreach($utilidades as $utilidad)
                <tr>
                    <td>{{ $utilidad['sucursal_nombre'] }}</td>
                    <td class="text-right">Bs. {{ number_format($utilidad['total_ventas'], 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($utilidad['total_compras'], 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($utilidad['utilidad'], 2) }}</td>
                    <td class="text-right">{{ number_format($utilidad['margen_porcentaje'], 2) }}%</td>
                    <td class="text-center">{{ $utilidad['cantidad_ventas'] }}</td>
                    <td class="text-center">{{ $utilidad['cantidad_compras'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>

</html>

