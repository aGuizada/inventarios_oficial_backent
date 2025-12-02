<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Traspaso extends Model
{
    use HasFactory;

    protected $table = 'traspasos';

    protected $fillable = [
        'codigo_traspaso',
        'sucursal_origen_id',
        'sucursal_destino_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'user_id', // Changed from usuario_id
        'fecha_solicitud',
        'fecha_aprobacion',
        'fecha_entrega',
        'tipo_traspaso',
        'estado',
        'motivo',
        'observaciones',
        'usuario_aprobador_id',
        'usuario_receptor_id',
    ];

    public function sucursalOrigen()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function almacenOrigen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function almacenDestino()
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioAprobador()
    {
        return $this->belongsTo(User::class, 'usuario_aprobador_id');
    }

    public function usuarioReceptor()
    {
        return $this->belongsTo(User::class, 'usuario_receptor_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleTraspaso::class);
    }

    public function historial()
    {
        return $this->hasMany(HistorialTraspaso::class);
    }
}
