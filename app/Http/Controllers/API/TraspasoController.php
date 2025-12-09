<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Traspaso;
use App\Models\DetalleTraspaso;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TraspasoController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Traspaso::with([
            'sucursalOrigen',
            'sucursalDestino',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'usuarioAprobador',
            'usuarioReceptor',
            'detalles.articulo'
        ]);

        $searchableFields = [
            'id',
            'codigo_traspaso',
            'tipo_traspaso',
            'estado',
            'motivo',
            'sucursalOrigen.nombre',
            'sucursalDestino.nombre',
            'user.name'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'fecha_solicitud', 'codigo_traspaso', 'estado'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
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
            'detalles.*.precio_costo' => 'nullable|numeric',
            'detalles.*.precio_venta' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Asegurar que el estado inicial sea PENDIENTE
            $traspasoData = $request->except('detalles');
            $traspasoData['estado'] = $traspasoData['estado'] ?? 'PENDIENTE';
            $traspaso = Traspaso::create($traspasoData);

            if ($request->has('detalles')) {
                foreach ($request->detalles as $detalle) {
                    DetalleTraspaso::create([
                        'traspaso_id' => $traspaso->id,
                        'articulo_id' => $detalle['articulo_id'],
                        'inventario_origen_id' => $detalle['inventario_id'],
                        'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                        'precio_costo' => $detalle['precio_costo'] ?? null,
                        'precio_venta' => $detalle['precio_venta'] ?? null,
                        'estado' => 'PENDIENTE',
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

    /**
     * Aprobar un traspaso
     * Descuenta del inventario origen
     */
    public function aprobar(Request $request, Traspaso $traspaso)
    {
        if ($traspaso->estado !== 'PENDIENTE') {
            return response()->json([
                'error' => 'Solo se pueden aprobar traspasos en estado PENDIENTE'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $traspaso->load('detalles.inventarioOrigen');
            
            // Validar stock disponible antes de aprobar
            foreach ($traspaso->detalles as $detalle) {
                $inventarioOrigen = Inventario::find($detalle->inventario_origen_id);
                
                if (!$inventarioOrigen) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El inventario origen del artículo {$detalle->articulo_id} no existe"
                    ], 400);
                }

                if ($inventarioOrigen->saldo_stock < $detalle->cantidad_solicitada) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Stock insuficiente para el artículo. Stock disponible: {$inventarioOrigen->saldo_stock}, solicitado: {$detalle->cantidad_solicitada}"
                    ], 400);
                }
            }

            // Descontar del inventario origen
            foreach ($traspaso->detalles as $detalle) {
                $inventarioOrigen = Inventario::find($detalle->inventario_origen_id);
                
                // Descontar cantidad
                $inventarioOrigen->saldo_stock -= $detalle->cantidad_solicitada;
                if ($inventarioOrigen->saldo_stock < 0) {
                    $inventarioOrigen->saldo_stock = 0;
                }
                $inventarioOrigen->save();

                // Actualizar detalle
                $detalle->cantidad_enviada = $detalle->cantidad_solicitada;
                $detalle->estado = 'ENVIADO';
                $detalle->save();
            }

            // Actualizar traspaso
            $traspaso->estado = 'EN_TRANSITO';
            $traspaso->fecha_aprobacion = now();
            $traspaso->usuario_aprobador_id = $request->user_id ?? auth()->id();
            $traspaso->save();

            DB::commit();
            $traspaso->load('detalles');
            return response()->json($traspaso);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al aprobar el traspaso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recibir un traspaso
     * Agrega al inventario destino
     */
    public function recibir(Request $request, Traspaso $traspaso)
    {
        if ($traspaso->estado !== 'EN_TRANSITO') {
            return response()->json([
                'error' => 'Solo se pueden recibir traspasos en estado EN_TRANSITO'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $traspaso->load('detalles');
            
            // Agregar al inventario destino
            foreach ($traspaso->detalles as $detalle) {
                // Buscar o crear inventario en el almacén destino
                $inventarioDestino = Inventario::where('almacen_id', $traspaso->almacen_destino_id)
                    ->where('articulo_id', $detalle->articulo_id)
                    ->first();

                if ($inventarioDestino) {
                    // Actualizar inventario existente
                    $inventarioDestino->cantidad += $detalle->cantidad_enviada;
                    $inventarioDestino->saldo_stock += $detalle->cantidad_enviada;
                    $inventarioDestino->save();
                } else {
                    // Crear nuevo inventario en destino
                    // Obtener datos del inventario origen para copiar fecha_vencimiento si existe
                    $inventarioOrigen = Inventario::find($detalle->inventario_origen_id);
                    
                    Inventario::create([
                        'almacen_id' => $traspaso->almacen_destino_id,
                        'articulo_id' => $detalle->articulo_id,
                        'cantidad' => $detalle->cantidad_enviada,
                        'saldo_stock' => $detalle->cantidad_enviada,
                        'fecha_vencimiento' => $inventarioOrigen->fecha_vencimiento ?? null
                    ]);
                }

                // Actualizar detalle
                $detalle->cantidad_recibida = $detalle->cantidad_enviada;
                $detalle->estado = 'RECIBIDO';
                $detalle->save();
            }

            // Actualizar traspaso
            $traspaso->estado = 'RECIBIDO';
            $traspaso->fecha_entrega = now();
            $traspaso->usuario_receptor_id = $request->user_id ?? auth()->id();
            $traspaso->save();

            DB::commit();
            $traspaso->load('detalles');
            return response()->json($traspaso);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al recibir el traspaso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar un traspaso
     */
    public function rechazar(Request $request, Traspaso $traspaso)
    {
        if ($traspaso->estado !== 'PENDIENTE') {
            return response()->json([
                'error' => 'Solo se pueden rechazar traspasos en estado PENDIENTE'
            ], 400);
        }

        $traspaso->estado = 'RECHAZADO';
        $traspaso->motivo = $request->motivo ?? 'Traspaso rechazado';
        $traspaso->usuario_aprobador_id = $request->user_id ?? auth()->id();
        $traspaso->save();

        $traspaso->load('detalles');
        return response()->json($traspaso);
    }
}
