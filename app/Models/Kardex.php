<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    use HasFactory;

    protected $table = 'kardex';

    protected $fillable = [
        'fecha',
        'tipo_movimiento',
        'documento_tipo',
        'documento_numero',
        'articulo_id',
        'almacen_id',
        'cantidad_entrada',
        'cantidad_salida',
        'cantidad_saldo',
        'costo_unitario',
        'costo_total',
        'precio_unitario',
        'precio_total',
        'observaciones',
        'usuario_id',
        'compra_id',
        'venta_id',
        'traspaso_id'
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad_entrada' => 'decimal:2',
        'cantidad_salida' => 'decimal:2',
        'cantidad_saldo' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_total' => 'decimal:2',
    ];

    // Relaciones
    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function traspaso()
    {
        return $this->belongsTo(Traspaso::class);
    }
}
