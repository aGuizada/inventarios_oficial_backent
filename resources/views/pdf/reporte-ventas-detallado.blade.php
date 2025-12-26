<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Detallado de Ventas con Ganancias</title>
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
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
        }
        .resumen-item.ganancia .value {
            color: #10b981;
        }
        .venta-item {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            page-break-inside: avoid;
        }
        .venta-header {
            background: #3B82F6;
            color: white;
            padding: 8px;
            border-radius: 3px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        .venta-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 10px;
            font-size: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th {
            background-color: #6b7280;
            color: white;
            padding: 6px;
            text-align: left;
            font-size: 8px;
        }
        td {
            border: 1px solid #ddd;
            padding: 5px;
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
        .total-row {
            background-color: #fef3c7;
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
        <h1>Reporte Detallado de Ventas con Ganancias</h1>
    </div>

    <div class="resumen">
        <div class="resumen-grid">
            <div class="resumen-item">
                <div class="label">Total Ventas</div>
                <div class="value">Bs. {{ number_format($resumen['total_ventas'], 2) }}</div>
            </div>
            <div class="resumen-item">
                <div class="label">Total Costos</div>
                <div class="value">Bs. {{ number_format($resumen['total_costos'], 2) }}</div>
            </div>
            <div class="resumen-item ganancia">
                <div class="label">Total Ganancias</div>
                <div class="value">Bs. {{ number_format($resumen['total_ganancias'], 2) }}</div>
            </div>
            <div class="resumen-item">
                <div class="label">Margen %</div>
                <div class="value">{{ number_format($resumen['margen_ganancia'], 2) }}%</div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; font-size: 9px;">
            <strong>Período:</strong> 
            {{ $resumen['fecha_desde'] ? date('d/m/Y', strtotime($resumen['fecha_desde'])) : 'Todos' }} 
            a 
            {{ $resumen['fecha_hasta'] ? date('d/m/Y', strtotime($resumen['fecha_hasta'])) : 'Todos' }}
            | 
            <strong>Cantidad de Ventas:</strong> {{ $resumen['cantidad_ventas'] }}
        </div>
    </div>

    @foreach($ventas as $venta)
        <div class="venta-item">
            <div class="venta-header">
                <div>
                    <strong>Venta #{{ $venta->id }}</strong> | 
                    {{ date('d/m/Y H:i', strtotime($venta->fecha_hora)) }} | 
                    Comprobante: {{ $venta->num_comprobante ?? 'N/A' }}
                </div>
                <div>
                    <strong>Total: Bs. {{ number_format($venta->total, 2) }}</strong>
                </div>
            </div>
            
            <div class="venta-info">
                <div><strong>Cliente:</strong> {{ $venta->cliente->nombre ?? 'Cliente General' }}</div>
                <div><strong>Vendedor:</strong> {{ $venta->user->name ?? 'N/A' }}</div>
                <div><strong>Tipo:</strong> {{ $venta->tipoVenta->nombre_tipo_ventas ?? 'N/A' }}</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Costo Unit.</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Costo Total</th>
                        <th class="text-right">Ganancia</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $subtotalVenta = 0;
                        $costoVenta = 0;
                        $gananciaVenta = 0;
                    @endphp
                    @foreach($venta->detalles as $detalle)
                        @php
                            $precioUnit = $detalle->precio;
                            $costoUnit = $detalle->articulo->precio_costo ?? 0;
                            $subtotal = $precioUnit * $detalle->cantidad;
                            $costo = $costoUnit * $detalle->cantidad;
                            $ganancia = $subtotal - $costo;
                            $subtotalVenta += $subtotal;
                            $costoVenta += $costo;
                            $gananciaVenta += $ganancia;
                        @endphp
                        <tr>
                            <td>{{ $detalle->articulo->nombre ?? 'N/A' }}</td>
                            <td class="text-center">{{ $detalle->cantidad }} {{ $detalle->unidad_medida ?? 'Unidad' }}</td>
                            <td class="text-right">Bs. {{ number_format($precioUnit, 2) }}</td>
                            <td class="text-right">Bs. {{ number_format($costoUnit, 2) }}</td>
                            <td class="text-right">Bs. {{ number_format($subtotal, 2) }}</td>
                            <td class="text-right">Bs. {{ number_format($costo, 2) }}</td>
                            <td class="text-right" style="color: #10b981; font-weight: bold;">Bs. {{ number_format($ganancia, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4"><strong>Total de la Venta</strong></td>
                        <td class="text-right"><strong>Bs. {{ number_format($subtotalVenta, 2) }}</strong></td>
                        <td class="text-right"><strong>Bs. {{ number_format($costoVenta, 2) }}</strong></td>
                        <td class="text-right" style="color: #10b981;"><strong>Bs. {{ number_format($gananciaVenta, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>

            @if($venta->pagos && $venta->pagos->count() > 0)
                <div style="margin-top: 10px; font-size: 8px;">
                    <strong>Métodos de Pago:</strong>
                    @foreach($venta->pagos as $pago)
                        {{ $pago->tipoPago->nombre_tipo_pago ?? 'N/A' }}: Bs. {{ number_format($pago->monto, 2) }}{{ !$loop->last ? ' | ' : '' }}
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>

