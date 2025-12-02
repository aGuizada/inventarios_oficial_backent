<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizacion';

    protected $fillable = [
        'cliente_id',
        'user_id', // Changed from usuario_id
        'almacen_id',
        'fecha_hora',
        'total',
        'validez',
        'plazo_entrega',
        'tiempo_entrega',
        'lugar_entrega',
        'forma_pago',
        'nota',
        'estado',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class);
    }
}
