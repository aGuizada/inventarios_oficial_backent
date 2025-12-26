<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma - Cotización #{{ $cotizacion->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #6b7280;
            padding-bottom: 15px;
        }
        .document-title {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin: 10px 0;
        }
        .date-time {
            font-size: 11px;
            color: #374151;
            margin-top: 8px;
        }
        .info-section {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .info-box {
            background: #f9fafb;
            padding: 12px;
            border-radius: 5px;
            border-left: 4px solid #6b7280;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 10px;
            color: #1f2937;
        }
        .info-box strong {
            color: #374151;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #6b7280;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 10px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-section {
            margin-top: 20px;
            margin-left: auto;
            width: 350px;
        }
        .total-text {
            font-size: 11px;
            margin-bottom: 8px;
            color: #374151;
        }
        .total-amount {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            text-align: right;
        }
        .note-section {
            margin-top: 25px;
            padding: 10px;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="document-title">COTIZACIÓN N° {{ $cotizacion->id }}</div>
        <div class="date-time">
            <strong>Fecha:</strong> {{ date('d/m/Y', strtotime($cotizacion->fecha_hora)) }} | 
            <strong>Hora:</strong> {{ date('H:i', strtotime($cotizacion->fecha_hora)) }}
        </div>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>CLIENTE</h3>
            <p><strong>Nombre:</strong> {{ $cotizacion->cliente->nombre ?? 'N/A' }}</p>
            <p><strong>Atendido por:</strong> {{ $cotizacion->user->name ?? 'N/A' }}</p>
        </div>
        <div class="info-box">
            <h3>INFORMACIÓN DE COTIZACIÓN</h3>
            <p><strong>Forma de pago:</strong> {{ $cotizacion->forma_pago ?? 'N/A' }}</p>
            <p><strong>Tiempo de entrega:</strong> {{ $cotizacion->tiempo_entrega ?? 'N/A' }} días</p>
            <p><strong>Lugar de entrega:</strong> {{ $cotizacion->lugar_entrega ?? 'N/A' }}</p>
        </div>
    </div>

    @if($cotizacion->validez || $cotizacion->plazo_entrega)
        <div class="info-box" style="margin-bottom: 20px;">
            <h3>VALIDEZ DE LA OFERTA</h3>
            @if($cotizacion->plazo_entrega)
                <p><strong>Días de validez:</strong> {{ $cotizacion->plazo_entrega }}</p>
            @endif
            @if($cotizacion->validez)
                <p><strong>Válido hasta:</strong> {{ date('d/m/Y', strtotime($cotizacion->validez)) }}</p>
            @endif
        </div>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th>ARTÍCULO</th>
                <th>MARCA</th>
                <th>MEDIDA</th>
                <th class="text-center">CANTIDAD</th>
                <th class="text-right">PRECIO UNITARIO</th>
                <th class="text-right">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->detalles as $detalle)
                @php
                    $precioUnit = $detalle->precio ?? 0;
                    $cantidad = $detalle->cantidad ?? 0;
                    $descuento = $detalle->descuento ?? 0;
                    $importe = ($precioUnit * $cantidad) - $descuento;
                @endphp
                <tr>
                    <td>{{ $detalle->articulo->nombre ?? 'N/A' }}</td>
                    <td>{{ $detalle->articulo->marca ? $detalle->articulo->marca->nombre : 'N/A' }}</td>
                    <td>{{ $detalle->articulo->medida ? $detalle->articulo->medida->nombre : 'PIEZA' }}</td>
                    <td class="text-center">{{ number_format($cantidad, 0) }}</td>
                    <td class="text-right">{{ number_format($precioUnit, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($importe, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-text">
            <strong>SON:</strong> {{ strtoupper(call_user_func($numeroALetras, (int)$cotizacion->total)) }} BOLIVIANOS
        </div>
        <div class="total-amount">
            TOTAL: {{ number_format($cotizacion->total, 2) }} Bs.
        </div>
    </div>

    <div class="note-section">
        <strong>Nota:</strong> {{ $cotizacion->nota ?: 'Sin observaciones adicionales' }}
    </div>
</body>
</html>
