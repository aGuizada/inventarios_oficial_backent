<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    use HasFactory;

    protected $table = 'detalle_compras';
    public $timestamps = false; // No timestamps in migration

    protected $fillable = [
        'compra_base_id',
        'articulo_id',
        'cantidad',
        'descuento',
        'precio',
    ];

    protected $appends = ['precio_unitario', 'subtotal'];

    public function compraBase()
    {
        return $this->belongsTo(CompraBase::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    // Accessor para mapear 'precio' a 'precio_unitario' para el frontend
    public function getPrecioUnitarioAttribute()
    {
        return $this->precio;
    }

    // Accessor para calcular el subtotal
    public function getSubtotalAttribute()
    {
        $subtotal = ($this->precio * $this->cantidad) - ($this->descuento ?? 0);
        return round($subtotal, 2);
    }
}
