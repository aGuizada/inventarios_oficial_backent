<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePago extends Model
{
    protected $table = 'detalle_pagos';

    protected $fillable = [
        'venta_id',
        'tipo_pago_id',
        'monto',
        'referencia'
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function tipoPago()
    {
        return $this->belongsTo(TipoPago::class);
    }
}
