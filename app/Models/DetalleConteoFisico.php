<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleConteoFisico extends Model
{
    protected $table = 'detalle_conteos_fisicos';

    protected $fillable = [
        'conteo_fisico_id',
        'articulo_id',
        'cantidad_sistema',
        'cantidad_contada',
        'diferencia',
        'costo_unitario'
    ];

    protected $casts = [
        'cantidad_sistema' => 'decimal:2',
        'cantidad_contada' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'costo_unitario' => 'decimal:2'
    ];

    public function conteo(): BelongsTo
    {
        return $this->belongsTo(ConteoFisico::class, 'conteo_fisico_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
