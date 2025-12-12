<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Reporte de Inventario</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            color: #1e3a8a;
        }

        .header p {
            margin: 5px 0;
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Reporte de Inventario</h1>
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Código</th>
                <th>Almacén</th>
                <th class="text-right">Stock Actual</th>
                <th class="text-right">Costo Unit.</th>
                <th class="text-right">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inventarios as $inv)
                <tr>
                    <td>{{ $inv->articulo->nombre ?? 'N/A' }}</td>
                    <td>{{ $inv->articulo->codigo ?? 'N/A' }}</td>
                    <td>{{ $inv->almacen->nombre_almacen ?? 'N/A' }}</td>
                    <td class="text-right">{{ $inv->saldo_stock }}</td>
                    <td class="text-right">{{ number_format($inv->articulo->precio_costo_unid ?? 0, 2) }}</td>
                    <td class="text-right">
                        {{ number_format($inv->saldo_stock * ($inv->articulo->precio_costo_unid ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Total de registros: {{ count($inventarios) }}</p>
    </div>
</body>

</html>