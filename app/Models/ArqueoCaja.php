<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArqueoCaja extends Model
{
    use HasFactory;

    protected $table = 'arqueo_cajas';

    protected $fillable = [
        'caja_id',
        'user_id', // Changed from usuario_id
        'billete200',
        'billete100',
        'billete50',
        'billete20',
        'billete10',
        'moneda5',
        'moneda2',
        'moneda1',
        'moneda050',
        'moneda020',
        'moneda010',
        'total_efectivo',
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
