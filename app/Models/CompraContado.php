<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraContado extends Model
{
    use HasFactory;

    protected $table = 'compras_contado';
    public $incrementing = false; // Primary key is not auto-incrementing

    protected $fillable = [
        'id',
        'fecha_pago',
        'metodo_pago',
        'referencia_pago',
    ];

    public function compraBase()
    {
        return $this->belongsTo(CompraBase::class, 'id');
    }
}
