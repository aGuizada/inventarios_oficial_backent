<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medida extends Model
{
    use HasFactory;

    protected $table = 'medidas';

    protected $fillable = [
        'nombre_medida',
        'estado',
    ];

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }
}
