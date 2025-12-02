<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    public function index()
    {
        $cotizaciones = Cotizacion::with(['cliente', 'user', 'almacen', 'detalles'])->get();
        return response()->json($cotizaciones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'user_id' => 'required|exists:users,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric',
            'validez' => 'nullable|string|max:100',
            'plazo_entrega' => 'nullable|string|max:100',
            'tiempo_entrega' => 'nullable|string|max:100',
            'lugar_entrega' => 'nullable|string|max:255',
            'forma_pago' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'estado' => 'nullable|string|max:50',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric',
            'detalles.*.descuento' => 'nullable|numeric',
            'detalles.*.subtotal' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::create($request->except('detalles'));

            if ($request->has('detalles')) {
                foreach ($request->detalles as $detalle) {
                    DetalleCotizacion::create([
                        'cotizacion_id' => $cotizacion->id,
                        'articulo_id' => $detalle['articulo_id'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'descuento' => $detalle['descuento'] ?? 0,
                        'subtotal' => $detalle['subtotal'],
                    ]);
                }
            }

            DB::commit();
            $cotizacion->load('detalles');
            return response()->json($cotizacion, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear la cotización'], 500);
        }
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente', 'user', 'almacen', 'detalles.articulo']);
        return response()->json($cotizacion);
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'user_id' => 'required|exists:users,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha_hora' => 'required|date',
            'total' => 'required|numeric',
            'validez' => 'nullable|string|max:100',
            'plazo_entrega' => 'nullable|string|max:100',
            'tiempo_entrega' => 'nullable|string|max:100',
            'lugar_entrega' => 'nullable|string|max:255',
            'forma_pago' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'estado' => 'nullable|string|max:50',
        ]);

        $cotizacion->update($request->all());

        return response()->json($cotizacion);
    }

    public function destroy(Cotizacion $cotizacion)
    {
        $cotizacion->delete();
        return response()->json(null, 204);
    }
}
