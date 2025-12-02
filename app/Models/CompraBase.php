<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraBase extends Model
{
    use HasFactory;

    protected $table = 'compras_base';

    protected $fillable = [
        'proveedor_id',
        'user_id', // Changed from usuario_id
        'tipo_comprobante',
        'serie_comprobante',
        'num_comprobante',
        'fecha_hora',
        'total',
        'estado',
        'almacen_id',
        'caja_id',
        'descuento_global',
        'tipo_compra',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, 'compra_base_id');
    }

    public function compraContado()
    {
        return $this->hasOne(CompraContado::class, 'id');
    }

    public function compraCredito()
    {
        return $this->hasOne(CompraCredito::class, 'id');
    }
}
