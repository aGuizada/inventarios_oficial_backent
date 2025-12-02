<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'codigoSucursal',
        'direccion',
        'correo',
        'telefono',
        'departamento',
        'estado',
        'responsable',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function almacenes()
    {
        return $this->hasMany(Almacen::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function cajas()
    {
        return $this->hasMany(Caja::class);
    }
}
