<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Ticket de Venta</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            margin: 0;
            padding: 5px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
        }

        .info {
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
        }

        .items {
            width: 100%;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
        }

        .items th {
            text-align: left;
            border-bottom: 1px solid #000;
        }

        .totals {
            text-align: right;
            margin-bottom: 10px;
        }

        .footer {
            text-align: center;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">INVENTARIOS OFICIAL</div>
        <div>NIT: 123456789</div>
        <div>Tel: 555-1234</div>
    </div>

    <div class="info">
        <div>F: {{ $venta->fecha_hora }}</div>
        <div>Ticket: {{ $venta->num_comprobante }}</div>
        <div>Cli: {{ Str::limit($venta->cliente->nombre, 20) }}</div>
        <div>Vend: {{ Str::limit($venta->user->name, 15) }}</div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 10%">Cant</th>
                <th style="width: 50%">Desc</th>
                <th style="width: 20%">P.U.</th>
                <th style="width: 20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->cantidad }}</td>
                    <td>{{ Str::limit($detalle->articulo->nombre, 15) }}</td>
                    <td>{{ number_format($detalle->precio, 2) }}</td>
                    <td>{{ number_format(($detalle->cantidad * $detalle->precio) - $detalle->descuento, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><strong>TOTAL: {{ number_format($venta->total, 2) }}</strong></div>
    </div>

    <div class="footer">
        <p>Gracias por su compra</p>
    </div>
</body>

</html>