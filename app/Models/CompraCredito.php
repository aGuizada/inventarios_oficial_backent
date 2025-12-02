<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraCredito extends Model
{
    use HasFactory;

    protected $table = 'compras_credito';
    public $incrementing = false; // Primary key is not auto-incrementing

    protected $fillable = [
        'id',
        'num_cuotas',
        'frecuencia_dias',
        'cuota_inicial',
        'tipo_pago_cuota',
        'dias_gracia',
        'interes_moratorio',
        'estado_credito',
    ];

    public function compraBase()
    {
        return $this->belongsTo(CompraBase::class, 'id');
    }

    public function cuotas()
    {
        return $this->hasMany(CompraCuota::class, 'compra_credito_id');
    }
}
