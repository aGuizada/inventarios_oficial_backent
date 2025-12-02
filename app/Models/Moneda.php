<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moneda extends Model
{
    use HasFactory;

    protected $table = 'monedas';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'pais',
        'simbolo',
        'tipo_cambio',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
