<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleCotizacion extends Model
{
    use HasFactory;

    protected $table = 'detalle_cotizacion';
    public $timestamps = false; // No timestamps in migration

    protected $fillable = [
        'cotizacion_id',
        'articulo_id',
        'cantidad',
        'precio',
        'descuento',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }
}
