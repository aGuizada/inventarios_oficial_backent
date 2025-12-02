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

    public function compraBase()
    {
        return $this->belongsTo(CompraBase::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }
}
