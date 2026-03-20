<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Traspaso\StoreTraspasoRequest;
use App\Http\Requests\Traspaso\UpdateTraspasoRequest;
use App\Http\Traits\HasPagination;
use App\Models\DetalleTraspaso;
use App\Models\Inventario;
use App\Models\Traspaso;
use App\Support\ApiError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TraspasoController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewAny', Traspaso::class);
        $query = Traspaso::with([
            'sucursalOrigen',
            'sucursalDestino',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'usuarioAprobador',
            'usuarioReceptor',
            'detalles.articulo',
        ])->forAuthenticatedList($user);

        $searchableFields = [
            'id',
            'codigo_traspaso',
            'tipo_traspaso',
            'estado',
            'motivo',
            'sucursalOrigen.nombre',
            'sucursalDestino.nombre',
            'user.name',
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'fecha_solicitud', 'codigo_traspaso', 'estado'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(StoreTraspasoRequest $request)
    {
        DB::beginTransaction();
        try {
            // Asegurar que el estado inicial sea PENDIENTE
            $traspasoData = $request->except(['detalles', 'user_id']);
            $traspasoData['user_id'] = $request->user()->id;
            $traspasoData['estado'] = $traspasoData['estado'] ?? 'PENDIENTE';

            // Formatear fecha_solicitud si viene en formato ISO
            if (isset($traspasoData['fecha_solicitud'])) {
                try {
                    $fechaSolicitud = \Carbon\Carbon::parse($traspasoData['fecha_solicitud']);
                    $traspasoData['fecha_solicitud'] = $fechaSolicitud->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    \Log::warning('Error al formatear fecha_solicitud: '.$e->getMessage());
                    // Si falla, usar la fecha actual
                    $traspasoData['fecha_solicitud'] = now()->format('Y-m-d H:i:s');
                }
            }

            $traspaso = Traspaso::create($traspasoData);

            if ($request->has('detalles') && is_array($request->detalles)) {
                foreach ($request->detalles as $detalle) {
                    // Validar que el inventario_id existe
                    if (! isset($detalle['inventario_id'])) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Error al crear el traspaso',
                            'message' => 'El campo inventario_id es requerido en los detalles',
                        ], 400);
                    }

                    // Validar que el inventario existe
                    $inventario = Inventario::find($detalle['inventario_id']);
                    if (! $inventario) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Error al crear el traspaso',
                            'message' => "El inventario con ID {$detalle['inventario_id']} no existe",
                        ], 400);
                    }

                    // Validar que el inventario pertenece al almacén origen
                    if ($inventario->almacen_id != $traspasoData['almacen_origen_id']) {
                        DB::rollBack();

                        return response()->json([
                            'error' => 'Error al crear el traspaso',
                            'message' => "El inventario con ID {$detalle['inventario_id']} no pertenece al almacén origen seleccionado",
                        ], 400);
                    }

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
            \Log::error('Error al crear traspaso', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiError::serverError($e, 'Error al crear el traspaso', 'TraspasoController@store');
        }
    }

    public function show(Traspaso $traspaso)
    {
        $this->authorize('view', $traspaso);
        $traspaso->load([
            'sucursalOrigen',
            'sucursalDestino',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'usuarioAprobador',
            'usuarioReceptor',
            'detalles.articulo',
            'historial',
        ]);

        return response()->json($traspaso);
    }

    public function update(UpdateTraspasoRequest $request, Traspaso $traspaso)
    {
        $camposPermitidos = [
            'almacen_origen_id', 'almacen_destino_id', 'fecha_solicitud', 'fecha_entrega',
            'tipo_traspaso', 'estado', 'motivo', 'observaciones',
            'usuario_aprobador_id', 'usuario_receptor_id',
        ];
        $traspaso->update($request->only($camposPermitidos));

        return response()->json($traspaso);
    }

    public function destroy(Traspaso $traspaso)
    {
        $this->authorize('delete', $traspaso);
        $traspaso->delete();

        return response()->json(null, 204);
    }

    /**
     * Aprobar un traspaso
     * Descuenta del inventario origen
     */
    public function aprobar(Request $request, Traspaso $traspaso)
    {
        $this->authorize('aprobar', $traspaso);
        if ($traspaso->estado !== 'PENDIENTE') {
            return response()->json([
                'error' => 'Solo se pueden aprobar traspasos en estado PENDIENTE',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $traspaso->load('detalles.inventarioOrigen');

            // Validar stock disponible antes de aprobar
            foreach ($traspaso->detalles as $detalle) {
                $inventarioOrigen = Inventario::find($detalle->inventario_origen_id);

                if (! $inventarioOrigen) {
                    DB::rollBack();

                    return response()->json([
                        'error' => "El inventario origen del artículo {$detalle->articulo_id} no existe",
                    ], 400);
                }

                if ($inventarioOrigen->saldo_stock < $detalle->cantidad_solicitada) {
                    DB::rollBack();

                    return response()->json([
                        'error' => "Stock insuficiente para el artículo. Stock disponible: {$inventarioOrigen->saldo_stock}, solicitado: {$detalle->cantidad_solicitada}",
                    ], 400);
                }
            }

            // Descontar del inventario origen
            foreach ($traspaso->detalles as $detalle) {
                $inventarioOrigen = Inventario::find($detalle->inventario_origen_id);

                if (! $inventarioOrigen) {
                    DB::rollBack();

                    return response()->json([
                        'error' => "El inventario origen del artículo {$detalle->articulo_id} no existe",
                    ], 400);
                }

                // Descontar cantidad y saldo_stock del inventario origen
                $cantidadADescontar = $detalle->cantidad_solicitada;

                $inventarioOrigen->cantidad -= $cantidadADescontar;
                $inventarioOrigen->saldo_stock -= $cantidadADescontar;

                // Asegurar que no queden valores negativos
                if ($inventarioOrigen->cantidad < 0) {
                    $inventarioOrigen->cantidad = 0;
                }
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
            $traspaso->usuario_aprobador_id = auth()->id();
            $traspaso->save();

            DB::commit();
            $traspaso->load('detalles');

            return response()->json($traspaso);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al aprobar el traspaso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recibir un traspaso
     * Agrega al inventario destino
     */
    public function recibir(Request $request, Traspaso $traspaso)
    {
        $this->authorize('recibir', $traspaso);
        if ($traspaso->estado !== 'EN_TRANSITO') {
            return response()->json([
                'error' => 'Solo se pueden recibir traspasos en estado EN_TRANSITO',
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
                        'fecha_vencimiento' => $inventarioOrigen->fecha_vencimiento ?? null,
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
            $traspaso->usuario_receptor_id = auth()->id();
            $traspaso->save();

            DB::commit();
            $traspaso->load('detalles');

            return response()->json($traspaso);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al recibir el traspaso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rechazar un traspaso
     */
    public function rechazar(Request $request, Traspaso $traspaso)
    {
        $this->authorize('rechazar', $traspaso);
        if ($traspaso->estado !== 'PENDIENTE') {
            return response()->json([
                'error' => 'Solo se pueden rechazar traspasos en estado PENDIENTE',
            ], 400);
        }

        $traspaso->estado = 'RECHAZADO';
        $traspaso->motivo = $request->motivo ?? 'Traspaso rechazado';
        $traspaso->usuario_aprobador_id = auth()->id();
        $traspaso->save();

        $traspaso->load('detalles');

        return response()->json($traspaso);
    }
}
