<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use HasFactory;

    protected $table = 'cajas';

    protected $fillable = [
        'sucursal_id',
        'user_id', // Changed from usuario_id
        'fecha_apertura',
        'fecha_cierre',
        'saldo_inicial',
        'depositos',
        'salidas',
        'ventas',
        'ventas_contado',
        'ventas_credito',
        'pagos_efectivo',
        'pagos_qr',
        'pagos_transferencia',
        'cuotas_ventas_credito',
        'compras_contado',
        'compras_credito',
        'saldo_faltante',
        'saldo_caja',
        'estado',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function arqueos()
    {
        return $this->hasMany(ArqueoCaja::class);
    }

    public function transacciones()
    {
        return $this->hasMany(TransaccionCaja::class);
    }
}
