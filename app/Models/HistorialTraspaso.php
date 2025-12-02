<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialTraspaso extends Model
{
    use HasFactory;

    protected $table = 'historial_traspasos';

    protected $fillable = [
        'traspaso_id',
        'evento',
        'descripcion',
        'user_id', // Changed from usuario_id
        'fecha_evento',
        'ip',
        'dispositivo',
    ];

    public function traspaso()
    {
        return $this->belongsTo(Traspaso::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
