<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Ticket de Venta</title>
    <style>
@page {
    size: 80mm auto;
    margin: 0;
}

body {
    width: 72mm;       /* 👈 ESTE ES EL VALOR CLAVE */
    max-width: 72mm;
    margin: 0;
    padding-left: 2mm;
    padding-right: 8mm; /* margen de seguridad */
    font-family: Courier, monospace;
    font-size: 9px;
}

.header, .footer {
    text-align: center;
    margin-bottom: 5px;
}

.company-name {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 3px;
    display: block;
}

.info {
    margin: 5px 0;
    border-bottom: 1px dashed #000;
    padding-bottom: 3px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding-left: 1.5mm;
    padding-right: 1.5mm;
}


th {
    font-weight: bold;
    letter-spacing: 0.5px;
}


.col-cant {
    width: 10%;
    text-align: center;
    padding-left: 0;
    padding-right: 1mm;
}

.col-desc {
    width: 42%;
    text-align: left;
    padding-left: 1mm;
}

.col-pu {
    width: 22%;
    text-align: right;
    padding-right: 1mm;
}

.col-total {
    width: 18%;
    text-align: right;
    padding-right: 4mm; /* margen de seguridad */
}


.totals {
    margin-top: 3px;
    padding-right: 6mm;
    text-align: right;
    font-weight: bold;
    font-size: 10px;
}

.total-letras {
    margin-top: 3px;
    font-size: 8px;
    text-align: left;
    line-height: 1.2;
}

.payment-method {
    margin-top: 2px;
    font-size: 8px;
    text-align: left;
}
</style>

</head>

<body>
    <div class="header">
        <div class="company-name"><strong>MC AUTOPARTS</strong></div>

        <div>Nº Comprobante: {{ $venta->num_comprobante }}</div>
    </div>

    <div class="info">
        <div>Fecha: {{ $venta->fecha_hora }}</div>

                <div>Cliente: {{ $venta->cliente ? Str::limit($venta->cliente->nombre, 20) : 'sin nombre' }}</div>
                
    </div>
    
    <table>
    <thead>
        <tr>
            <th class="col-cant">Cant</th>
            <th class="col-desc">Detalle</th>
            <th class="col-pu">P.Unt.</th>
            <th class="col-total">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($venta->detalles as $detalle)
        <tr>
            <td class="col-cant">
                @php
                    $unidad = strtolower(trim($detalle->unidad_medida ?? 'unidad'));
                    $cantidad = (float) $detalle->cantidad;
                    
                    // Verificar si el artículo tiene medida en metros o centímetros
                    $esMetroOCentimetro = false;
                    if ($detalle->articulo && $detalle->articulo->medida) {
                        $nombreMedida = strtolower(trim($detalle->articulo->medida->nombre_medida ?? $detalle->articulo->medida->nombre ?? ''));
                        if (strpos($nombreMedida, 'metro') !== false || strpos($nombreMedida, 'centimetro') !== false || strpos($nombreMedida, 'centímetro') !== false) {
                            $esMetroOCentimetro = true;
                        }
                    }
                    
                    // Verificar si la cantidad tiene decimales significativos
                    // Redondear a 3 decimales primero para evitar problemas de precisión
                    $cantidadRedondeada = round($cantidad, 3);
                    $parteEntera = floor($cantidadRedondeada);
                    $parteDecimal = abs($cantidadRedondeada - $parteEntera);
                    $tieneDecimales = $parteDecimal > 0.0001;
                    
                    // SIEMPRE mostrar con 2 decimales si:
                    // 1. La unidad es metro o centímetro
                    // 2. El artículo tiene medida en metros/centímetros
                    // 3. La cantidad tiene decimales (aunque la unidad sea "Unidad")
                    // Si tiene decimales, SIEMPRE mostrar con 2 decimales sin importar la unidad
                    if ($unidad === 'centimetro' || $unidad === 'metro' || $unidad === 'metros' || $esMetroOCentimetro || $tieneDecimales) {
                        // Siempre mostrar con 2 decimales
                        echo number_format($cantidad, 2, '.', '');
                    } else {
                        // Solo para Unidad y Paquete sin decimales, mostrar como entero
                        echo number_format($cantidad, 0, '.', '');
                    }
                @endphp
            </td>
            <td class="col-desc">
                {{ Str::limit($detalle->articulo->nombre, 100) }}
                @if($detalle->articulo->codigo)
                    ({{ $detalle->articulo->codigo }})
                @endif
                @if($detalle->articulo->marca && $detalle->articulo->marca->nombre)
                    <br><span style="font-size: 7px; color: #666;">Marca: {{ Str::limit($detalle->articulo->marca->nombre, 30) }}</span>
                @endif
            </td>
            <td class="col-pu">{{ number_format($detalle->precio, 2) }}</td>
            <td class="col-total">
                {{ number_format(($detalle->cantidad * $detalle->precio) - $detalle->descuento, 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>


    <div class="totals">
        <div><strong>TOTAL: {{ number_format($venta->total, 2) }}</strong></div>
    </div>

    <div class="total-letras">
        @php
            $total = (float) $venta->total;
            $parteEntera = (int) $total;
            $parteDecimal = round(($total - $parteEntera) * 100);
            $centavos = str_pad($parteDecimal, 2, '0', STR_PAD_LEFT);
            
            // Usar el número en letras que viene del controlador
            // Si no viene, mostrar solo el número
            $textoNumero = isset($numeroEnLetras) && !empty($numeroEnLetras) ? $numeroEnLetras : 'CERO';
        @endphp
        <div><strong>SON:</strong> {{ $textoNumero }} {{ $centavos }}/100 Bolivianos</div>
        
    </div>

    <div class="footer">
        <p>Gracias por su compra</p>
    </div>
</body>

</html>