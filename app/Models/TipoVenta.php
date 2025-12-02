<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVenta extends Model
{
    use HasFactory;

    protected $table = 'tipo_ventas';

    protected $fillable = [
        'nombre_tipo_ventas',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
