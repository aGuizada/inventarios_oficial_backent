<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleTraspaso extends Model
{
    use HasFactory;

    protected $table = 'detalle_traspasos';

    protected $fillable = [
        'traspaso_id',
        'articulo_id',
        'inventario_origen_id',
        'cantidad_solicitada',
        'cantidad_enviada',
        'cantidad_recibida',
        'precio_costo',
        'precio_venta',
        'lote',
        'fecha_vencimiento',
        'observaciones',
        'estado',
    ];

    public function traspaso()
    {
        return $this->belongsTo(Traspaso::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function inventarioOrigen()
    {
        return $this->belongsTo(Inventario::class, 'inventario_origen_id');
    }
}
