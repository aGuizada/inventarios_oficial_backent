<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionVenta extends Model
{
    protected $table = 'devoluciones_ventas';

    protected $fillable = [
        'venta_id',
        'fecha',
        'motivo',
        'monto_devuelto',
        'estado',
        'observaciones',
        'usuario_id'
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_devuelto' => 'decimal:2'
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleDevolucionVenta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
