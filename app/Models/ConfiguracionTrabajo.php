<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionTrabajo extends Model
{
    use HasFactory;

    protected $table = 'configuracion_trabajos';

    protected $fillable = [
        'gestion',
        'codigo_productos',
        'almacen_predeterminado',
        'maximo_descuento',
        'valuacion_inventario',
        'backup_automatico',
        'ruta_backup',
        'saldos_negativos',
        'separador_decimales',
        'mostrar_costos',
        'mostrar_proveedores',
        'mostrar_saldos_stock',
        'actualizar_iva',
        'permitir_devolucion',
        'editar_nro_doc',
        'registro_cliente_obligatorio',
        'buscar_cliente_por_codigo',
        'moneda_principal_id',
        'moneda_venta_id',
        'moneda_compra_id',
        'tiempo_min_caducidad_articulo',
    ];

    public function monedaPrincipal()
    {
        return $this->belongsTo(Moneda::class, 'moneda_principal_id');
    }

    public function monedaVenta()
    {
        return $this->belongsTo(Moneda::class, 'moneda_venta_id');
    }

    public function monedaCompra()
    {
        return $this->belongsTo(Moneda::class, 'moneda_compra_id');
    }
}
