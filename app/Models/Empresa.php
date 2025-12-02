<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'nit',
        'logo',
    ];

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }

    public function monedas()
    {
        return $this->hasMany(Moneda::class);
    }
}
