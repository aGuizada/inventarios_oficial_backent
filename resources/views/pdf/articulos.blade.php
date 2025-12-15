<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Reporte de Artículos</title>
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
            color: #142c70ff;
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
        <h1>Reporte de Artículos</h1>
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Marca</th>
                <th class="text-right">Precio Venta</th>
                <th class="text-right">Stock</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($articulos as $articulo)
                <tr>
                    <td>{{ $articulo->codigo }}</td>
                    <td>{{ $articulo->nombre }}</td>
                    <td>{{ $articulo->categoria->nombre ?? 'N/A' }}</td>
                    <td>{{ $articulo->marca->nombre_marca ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($articulo->precio_venta_unid, 2) }}</td>
                    <td class="text-right">{{ $articulo->inventario->sum('saldo_stock') }}</td>
                    <td>{{ $articulo->estado ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Total de registros: {{ count($articulos) }}</p>
    </div>
</body>

</html>