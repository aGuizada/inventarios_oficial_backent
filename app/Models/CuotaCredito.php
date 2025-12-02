<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuotaCredito extends Model
{
    use HasFactory;

    protected $table = 'cuotas_credito';

    protected $fillable = [
        'credito_id',
        'cobrador_id',
        'numero_cuota',
        'fecha_pago',
        'fecha_cancelado',
        'precio_cuota',
        'saldo_restante',
        'estado',
    ];

    public function credito()
    {
        return $this->belongsTo(CreditoVenta::class, 'credito_id');
    }

    public function cobrador()
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }
}
