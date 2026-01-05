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
}

.company-name {
    font-weight: bold;
    font-size: 10px;
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
        <div class="company-name">MC AUTOPARTS</div>
        <div>Ticket: {{ $venta->num_comprobante }}</div>
    </div>

    <div class="info">
        <div>Fecha: {{ $venta->fecha_hora }}</div>
                <div>Cliente: {{ $venta->cliente ? Str::limit($venta->cliente->nombre, 20) : 'sin nombre' }}</div>
    </div>

    <table>
    <thead>
        <tr>
            <th class="col-cant">Cant</th>
            <th class="col-desc">Descripcion</th>
            <th class="col-pu">P.U.</th>
            <th class="col-total">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($venta->detalles as $detalle)
        <tr>
            <td class="col-cant">{{ $detalle->cantidad }}</td>
            <td class="col-desc">{{ Str::limit($detalle->articulo->nombre, 100) }}</td>
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
            $total = $venta->total;
            $parteEntera = (int) $total;
            $parteDecimal = round(($total - $parteEntera) * 100);
            $centavos = str_pad($parteDecimal, 2, '0', STR_PAD_LEFT);
            $numeroEnLetras = ucfirst(strtolower(call_user_func($numeroALetras, $parteEntera)));
        @endphp
        <div><strong>SON:</strong> {{ $numeroEnLetras }} {{ $centavos }}/100 Bolivianos</div>
        <div class="payment-method">
            <strong>Forma de pago:</strong>
            @php
                $formaPagoTexto = '';
                $tiposPago = [];
                
                // Primero intentar obtener de los pagos detallados
                if ($venta->pagos && $venta->pagos->count() > 0) {
                    foreach($venta->pagos as $pago) {
                        if ($pago->tipoPago && $pago->tipoPago->nombre) {
                            $nombrePago = trim($pago->tipoPago->nombre);
                            if (!empty($nombrePago)) {
                                $tiposPago[] = $nombrePago;
                            }
                        }
                    }
                }
                
                // Si no hay pagos detallados, usar el tipoPago de la venta
                if (count($tiposPago) == 0 && $venta->tipoPago && $venta->tipoPago->nombre) {
                    $nombrePago = trim($venta->tipoPago->nombre);
                    if (!empty($nombrePago)) {
                        $tiposPago[] = $nombrePago;
                    }
                }
                
                // Procesar y mostrar los tipos de pago
                if (count($tiposPago) > 0) {
                    $tiposPago = array_unique($tiposPago);
                    // Ordenar para que Efectivo aparezca primero si existe
                    if (count($tiposPago) > 1) {
                        usort($tiposPago, function($a, $b) {
                            $orden = ['Efectivo' => 1, 'EFECTIVO' => 1, 'QR' => 2, 'QR SIMPLE' => 2];
                            $ordenA = $orden[$a] ?? 99;
                            $ordenB = $orden[$b] ?? 99;
                            return $ordenA <=> $ordenB;
                        });
                        $formaPagoTexto = implode(' / ', $tiposPago);
                    } else {
                        $formaPagoTexto = $tiposPago[0];
                    }
                } else {
                    // Fallback si no hay nada
                    $formaPagoTexto = 'QR SIMPLE';
                }
            @endphp
            {{ $formaPagoTexto }}
        </div>
    </div>

    <div class="footer">
        <p>Gracias por su compra</p>
    </div>
</body>

</html>