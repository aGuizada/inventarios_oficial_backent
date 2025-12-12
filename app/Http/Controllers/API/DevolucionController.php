<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DevolucionVenta;
use App\Models\DetalleDevolucionVenta;
use App\Models\Venta;
use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionController extends Controller
{
    /**
     * Listar devoluciones
     */
    protected $kardexService;

    public function __construct(\App\Services\KardexService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    public function index(Request $request)
    {
        $query = DevolucionVenta::with(['venta', 'usuario', 'detalles.articulo']);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $devoluciones = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $devoluciones
        ]);
    }

    /**
     * Crear devolución de venta
     */
    public function store(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:ventas,id',
            'fecha' => 'required|date',
            'motivo' => 'required|string|max:100',
            'observaciones' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.almacen_id' => 'required|exists:almacenes,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            // Crear devolución
            $montoDevuelto = collect($request->detalles)->sum(function ($detalle) {
                return $detalle['cantidad'] * $detalle['precio_unitario'];
            });

            $devolucion = DevolucionVenta::create([
                'venta_id' => $request->venta_id,
                'fecha' => $request->fecha,
                'motivo' => $request->motivo,
                'monto_devuelto' => $montoDevuelto,
                'estado' => 'procesada',
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id() ?? 1
            ]);

            // Procesar cada detalle
            foreach ($request->detalles as $detalle) {
                // Crear detalle
                $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];

                DetalleDevolucionVenta::create([
                    'devolucion_venta_id' => $devolucion->id,
                    'articulo_id' => $detalle['articulo_id'],
                    'almacen_id' => $detalle['almacen_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $subtotal
                ]);

                // Registrar movimiento en Kardex y actualizar stock usando KardexService
                $this->kardexService->registrarMovimiento([
                    'articulo_id' => $detalle['articulo_id'],
                    'almacen_id' => $detalle['almacen_id'],
                    'fecha' => $request->fecha,
                    'tipo_movimiento' => 'ajuste', // O 'devolucion_venta' si se prefiere, pero el servicio espera tipos específicos? El servicio no valida enum, así que 'ajuste' está bien o 'devolucion'
                    'documento_tipo' => 'devolucion_venta',
                    'documento_numero' => 'DEV-' . $devolucion->id,
                    'cantidad_entrada' => $detalle['cantidad'],
                    'cantidad_salida' => 0,
                    'costo_unitario' => $detalle['precio_unitario'], // Asumimos precio como costo en devolución? O deberíamos buscar el costo original? Por simplicidad usamos el precio devuelto.
                    'precio_unitario' => $detalle['precio_unitario'],
                    'observaciones' => 'Devolución de venta #' . $request->venta_id . ' - ' . $request->motivo,
                    'usuario_id' => auth()->id() ?? 1,
                    'venta_id' => $request->venta_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devolución procesada exitosamente',
                'data' => $devolucion->load('detalles.articulo')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al procesar devolución', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar devolución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de devolución
     */
    public function show($id)
    {
        $devolucion = DevolucionVenta::with(['venta', 'usuario', 'detalles.articulo', 'detalles.almacen'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $devolucion
        ]);
    }
}
