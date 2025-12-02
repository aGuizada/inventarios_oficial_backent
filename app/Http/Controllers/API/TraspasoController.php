<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Traspaso;
use App\Models\DetalleTraspaso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TraspasoController extends Controller
{
    public function index()
    {
        $traspasos = Traspaso::with([
            'sucursalOrigen',
            'sucursalDestino',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'usuarioAprobador',
            'usuarioReceptor',
            'detalles'
        ])->get();
        return response()->json($traspasos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo_traspaso' => 'required|string|max:50|unique:traspasos',
            'sucursal_origen_id' => 'required|exists:sucursales,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id',
            'almacen_origen_id' => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id',
            'user_id' => 'required|exists:users,id',
            'fecha_solicitud' => 'required|date',
            'fecha_aprobacion' => 'nullable|date',
            'fecha_entrega' => 'nullable|date',
            'tipo_traspaso' => 'nullable|string|max:50',
            'estado' => 'nullable|string|max:50',
            'motivo' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'usuario_aprobador_id' => 'nullable|exists:users,id',
            'usuario_receptor_id' => 'nullable|exists:users,id',
            'detalles' => 'nullable|array',
            'detalles.*.articulo_id' => 'required|exists:articulos,id',
            'detalles.*.inventario_id' => 'required|exists:inventarios,id',
            'detalles.*.cantidad_solicitada' => 'required|integer|min:1',
            'detalles.*.cantidad_aprobada' => 'nullable|integer',
            'detalles.*.cantidad_recibida' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $traspaso = Traspaso::create($request->except('detalles'));

            if ($request->has('detalles')) {
                foreach ($request->detalles as $detalle) {
                    DetalleTraspaso::create([
                        'traspaso_id' => $traspaso->id,
                        'articulo_id' => $detalle['articulo_id'],
                        'inventario_id' => $detalle['inventario_id'],
                        'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                        'cantidad_aprobada' => $detalle['cantidad_aprobada'] ?? null,
                        'cantidad_recibida' => $detalle['cantidad_recibida'] ?? null,
                        'estado' => 'pendiente',
                    ]);
                }
            }

            DB::commit();
            $traspaso->load('detalles');
            return response()->json($traspaso, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear el traspaso'], 500);
        }
    }

    public function show(Traspaso $traspaso)
    {
        $traspaso->load([
            'sucursalOrigen',
            'sucursalDestino',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'usuarioAprobador',
            'usuarioReceptor',
            'detalles.articulo',
            'historial'
        ]);
        return response()->json($traspaso);
    }

    public function update(Request $request, Traspaso $traspaso)
    {
        $request->validate([
            'codigo_traspaso' => 'required|string|max:50|unique:traspasos,codigo_traspaso,' . $traspaso->id,
            'sucursal_origen_id' => 'required|exists:sucursales,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id',
            'almacen_origen_id' => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id',
            'user_id' => 'required|exists:users,id',
            'fecha_solicitud' => 'required|date',
            'fecha_aprobacion' => 'nullable|date',
            'fecha_entrega' => 'nullable|date',
            'tipo_traspaso' => 'nullable|string|max:50',
            'estado' => 'nullable|string|max:50',
            'motivo' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'usuario_aprobador_id' => 'nullable|exists:users,id',
            'usuario_receptor_id' => 'nullable|exists:users,id',
        ]);

        $traspaso->update($request->all());

        return response()->json($traspaso);
    }

    public function destroy(Traspaso $traspaso)
    {
        $traspaso->delete();
        return response()->json(null, 204);
    }
}
