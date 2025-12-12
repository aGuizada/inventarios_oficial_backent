<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConteoFisico extends Model
{
    protected $table = 'conteos_fisicos';

    protected $fillable = [
        'almacen_id',
        'fecha_conteo',
        'responsable',
        'estado',
        'observaciones',
        'usuario_id'
    ];

    protected $casts = [
        'fecha_conteo' => 'date'
    ];

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleConteoFisico::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
