<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Comprobante de Venta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f2f2f2;
        }

        .totals-table {
            width: 100%;
            text-align: right;
        }

        .totals-table td {
            padding: 5px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">INVENTARIOS OFICIAL</div>
        <div>NIT: 123456789</div>
        <div>Dirección: Calle Principal #123</div>
        <div>Teléfono: 555-1234</div>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Cliente:</strong> {{ $venta->cliente->nombre }}</td>
            <td><strong>Fecha:</strong> {{ $venta->fecha_hora }}</td>
        </tr>
        <tr>
            <td><strong>NIT/CI:</strong> {{ $venta->cliente->num_documento }}</td>
            <td><strong>Comprobante:</strong> {{ $venta->tipo_comprobante }}
                {{ $venta->serie_comprobante }}-{{ $venta->num_comprobante }}
            </td>
        </tr>
        <tr>
            <td><strong>Vendedor:</strong> {{ $venta->user->name }}</td>
            <td><strong>Forma de Pago:</strong> {{ $venta->tipoPago->nombre }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Cant.</th>
                <th>Descripción</th>
                <th>Unidad</th>
                <th>Precio Unit.</th>
                <th>Desc.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>
                        @php
                            $unidad = strtolower($detalle->unidad_medida ?? 'unidad');
                            // Para centímetros y metros, mostrar 2 decimales (0.30, 1.00)
                            if ($unidad === 'centimetro' || $unidad === 'metro' || $unidad === 'metros') {
                                echo number_format($detalle->cantidad, 2, '.', '');
                            } else {
                                // Para Unidad y Paquete, mostrar enteros si es posible
                                $cantidad = (float) $detalle->cantidad;
                                if ($cantidad == floor($cantidad)) {
                                    echo number_format($cantidad, 0, '.', '');
                                } else {
                                    echo number_format($cantidad, 2, '.', '');
                                }
                            }
                        @endphp
                    </td>
                    <td>
                        {{ $detalle->articulo->nombre }}
                        @if($detalle->articulo->codigo)
                            ({{ $detalle->articulo->codigo }})
                        @endif
                    </td>
                    <td>{{ $detalle->unidad_medida }}</td>
                    <td>{{ number_format($detalle->precio, 2) }}</td>
                    <td>{{ number_format($detalle->descuento, 2) }}</td>
                    <td>{{ number_format(($detalle->cantidad * $detalle->precio) - $detalle->descuento, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td><strong>Total a Pagar:</strong></td>
            <td><strong>{{ number_format($venta->total, 2) }}</strong></td>
        </tr>
    </table>

    <div class="footer">
        <p>Gracias por su compra</p>
        <p>Este documento es un comprobante de venta válido.</p>
    </div>
</body>

</html>