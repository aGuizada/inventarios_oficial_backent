<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    use HasFactory;

    protected $table = 'inventarios';

    protected $fillable = [
        'almacen_id',
        'articulo_id',
        'fecha_vencimiento',
        'saldo_stock',
        'cantidad',
    ];

    protected $casts = [
        'saldo_stock' => 'decimal:3',
        'cantidad' => 'decimal:3',
    ];

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class);
    }
}
