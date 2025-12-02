<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Articulo extends Model
{
    use HasFactory;

    protected $table = 'articulos';

    protected $fillable = [
        'categoria_id',
        'proveedor_id',
        'medida_id',
        'marca_id',
        'industria_id',
        'codigo',
        'nombre',
        'unidad_envase',
        'precio_costo_unid',
        'precio_costo_paq',
        'precio_venta',
        'precio_uno',
        'precio_dos',
        'precio_tres',
        'precio_cuatro',
        'stock',
        'descripcion',
        'estado',
        'costo_compra',
        'vencimiento',
        'fotografia',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function medida()
    {
        return $this->belongsTo(Medida::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function industria()
    {
        return $this->belongsTo(Industria::class);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }
}
