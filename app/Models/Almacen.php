<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasFactory;

    protected $table = 'almacenes';

    protected $fillable = [
        'nombre_almacen',
        'ubicacion',
        'sucursal_id',
        'telefono',
        'estado',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function compras()
    {
        return $this->hasMany(CompraBase::class);
    }
}
