<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditoVenta extends Model
{
    use HasFactory;

    protected $table = 'credito_ventas';

    protected $fillable = [
        'venta_id',
        'cliente_id',
        'numero_cuotas',
        'tiempo_dias_cuota',
        'total',
        'estado',
        'proximo_pago',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuotas()
    {
        return $this->hasMany(CuotaCredito::class, 'credito_id');
    }
}
