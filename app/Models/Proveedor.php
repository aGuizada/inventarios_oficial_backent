<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'tipo_documento',
        'num_documento',
        'direccion',
        'telefono',
        'email',
        'estado',
    ];

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

    public function compras()
    {
        return $this->hasMany(CompraBase::class);
    }
}
