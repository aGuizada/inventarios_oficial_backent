<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Industria extends Model
{
    use HasFactory;

    protected $table = 'industrias';
    public $timestamps = false; // No timestamps in migration

    protected $fillable = [
        'nombre',
        'estado',
    ];

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }
}
