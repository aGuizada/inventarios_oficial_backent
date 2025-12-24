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

    /**
     * Calcula el saldo disponible de la caja
     *
     * @return float
     */
    public function calcularSaldoDisponible()
    {
        $saldoInicial = (float) ($this->saldo_inicial ?? 0);
        $depositos = (float) ($this->depositos ?? 0);
        $ventas = (float) ($this->ventas ?? 0);
        $pagosEfectivo = (float) ($this->pagos_efectivo ?? 0);
        $pagosQr = (float) ($this->pagos_qr ?? 0);
        $pagosTransferencia = (float) ($this->pagos_transferencia ?? 0);
        $cuotasVentasCredito = (float) ($this->cuotas_ventas_credito ?? 0);
        $salidas = (float) ($this->salidas ?? 0);
        $comprasContado = (float) ($this->compras_contado ?? 0);
        $comprasCredito = (float) ($this->compras_credito ?? 0);
        $saldoFaltante = (float) ($this->saldo_faltante ?? 0);
        
        $saldoDisponible = $saldoInicial + $depositos + $ventas + $pagosEfectivo + $pagosQr + 
                          $pagosTransferencia + $cuotasVentasCredito - $salidas - 
                          $comprasContado - $comprasCredito - $saldoFaltante;
        
        return $saldoDisponible;
    }
}
