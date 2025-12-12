<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex de Inventario</title>
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

        .info {
            margin-bottom: 15px;
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

        .totales {
            margin-top: 20px;
            font-weight: bold;
        }

        .badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
        }

        .badge-compra {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .badge-venta {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .badge-ajuste {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-traspaso {
            background-color: #E9D5FF;
            color: #6B21A8;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Kardex de Inventario - {{ ucfirst($tipo) }}</h1>
        <p>Generado el: {{ $fecha_generacion }}</p>
    </div>

    <div class="info">
        @if($filtros['articulo_id'] ?? false)
            <p><strong>Artículo:</strong> {{ $kardex->first()->articulo->nombre ?? 'N/A' }}</p>
        @endif
        @if($filtros['almacen_id'] ?? false)
            <p><strong>Almacén:</strong> {{ $kardex->first()->almacen->nombre_almacen ?? 'N/A' }}</p>
        @endif
        @if($filtros['fecha_desde'] || $filtros['fecha_hasta'])
            <p><strong>Período:</strong>
                @if($filtros['fecha_desde']) Desde: {{ $filtros['fecha_desde'] }} @endif
                @if($filtros['fecha_hasta']) Hasta: {{ $filtros['fecha_hasta'] }} @endif
            </p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Documento</th>
                <th>Artículo</th>
                <th>Almacén</th>
                <th class="text-right">Entrada</th>
                <th class="text-right">Salida</th>
                <th class="text-right">Saldo</th>
                @if($tipo === 'valorado')
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Costo Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($kardex as $mov)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($mov->fecha)) }}</td>
                    <td>
                        <span class="badge badge-{{ str_replace('_', '-', $mov->tipo_movimiento) }}">
                            {{ $mov->tipo_movimiento }}
                        </span>
                    </td>
                    <td>{{ $mov->documento_numero }}</td>
                    <td>{{ $mov->articulo->nombre ?? 'N/A' }}</td>
                    <td>{{ $mov->almacen->nombre_almacen ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($mov->cantidad_entrada, 2) }}</td>
                    <td class="text-right">{{ number_format($mov->cantidad_salida, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($mov->cantidad_saldo, 2) }}</strong></td>
                    @if($tipo === 'valorado')
                        <td class="text-right">{{ number_format($mov->costo_unitario, 2) }}</td>
                        <td class="text-right">{{ number_format($mov->costo_total, 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <p>Total Entradas: {{ number_format($totales['total_entradas'], 2) }}</p>
        <p>Total Salidas: {{ number_format($totales['total_salidas'], 2) }}</p>
        @if($tipo === 'valorado')
            <p>Total Costos: Bs. {{ number_format($totales['total_costos'], 2) }}</p>
            <p>Total Ventas: Bs. {{ number_format($totales['total_ventas'], 2) }}</p>
        @endif
    </div>
</body>

</html>