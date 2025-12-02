<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraCuota extends Model
{
    use HasFactory;

    protected $table = 'compras_cuotas';

    protected $fillable = [
        'compra_credito_id',
        'numero_cuota',
        'fecha_vencimiento',
        'monto_cuota',
        'monto_pagado',
        'saldo_pendiente',
        'fecha_pago',
        'estado',
    ];

    public function compraCredito()
    {
        return $this->belongsTo(CompraCredito::class);
    }
}
