<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaccionCaja extends Model
{
    use HasFactory;

    protected $table = 'transacciones_cajas';

    protected $fillable = [
        'caja_id',
        'user_id', // Changed from usuario_id
        'fecha',
        'transaccion',
        'importe',
        'descripcion',
        'referencia',
    ];

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
