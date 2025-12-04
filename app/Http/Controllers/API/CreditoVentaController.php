<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CreditoVenta;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CreditoVentaController extends Controller
{
    public function index()
    {
        try {
            $creditoVentas = CreditoVenta::with(['venta.cliente', 'venta.detalles.articulo', 'cliente', 'cuotas'])->get();
            return response()->json([
                'success' => true,
                'data' => $creditoVentas
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener créditos venta:', [
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener créditos venta',
                'data' => []
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            \Log::info('=== INICIO: Crear crédito venta ===');
            \Log::info('Request completo:', $request->all());
            \Log::info('Headers:', $request->headers->all());
            
            $validated = $request->validate([
                'venta_id' => 'required|exists:ventas,id|unique:credito_ventas,venta_id',
                'cliente_id' => 'required|exists:clientes,id',
                'numero_cuotas' => 'required|integer|min:1',
                'tiempo_dias_cuota' => 'required|integer|min:1',
                'total' => 'required|numeric|min:0',
                'estado' => 'nullable|string|max:191',
                'proximo_pago' => 'nullable|date',
            ]);
            
            \Log::info('Validación exitosa. Datos validados:', $validated);

            // Preparar datos solo con los campos permitidos
            $data = [
                'venta_id' => $validated['venta_id'],
                'cliente_id' => $validated['cliente_id'],
                'numero_cuotas' => $validated['numero_cuotas'],
                'tiempo_dias_cuota' => $validated['tiempo_dias_cuota'],
                'total' => $validated['total'],
                'estado' => $validated['estado'] ?? 'Pendiente',
            ];
            
            // Manejar proximo_pago: convertir string a Carbon si es necesario
            if (isset($validated['proximo_pago']) && $validated['proximo_pago']) {
                try {
                    $data['proximo_pago'] = Carbon::parse($validated['proximo_pago'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    \Log::warning('Error al parsear proximo_pago, usando valor directo:', ['valor' => $validated['proximo_pago'], 'error' => $e->getMessage()]);
                    $data['proximo_pago'] = $validated['proximo_pago'];
                }
            } else {
                $data['proximo_pago'] = null;
            }

            \Log::info('Creando crédito venta con datos:', $data);
            \Log::info('Datos recibidos en request:', $request->all());

            // Verificar que la venta existe antes de crear el crédito
            $ventaExiste = \DB::table('ventas')->where('id', $data['venta_id'])->exists();
            \Log::info('¿La venta existe?', ['venta_id' => $data['venta_id'], 'existe' => $ventaExiste]);
            
            if (!$ventaExiste) {
                \Log::error('Error: La venta no existe en la base de datos', ['venta_id' => $data['venta_id']]);
                throw new \Exception('La venta especificada no existe');
            }

            // Verificar si ya existe un crédito para esta venta
            $creditoExistente = \DB::table('credito_ventas')->where('venta_id', $data['venta_id'])->first();
            if ($creditoExistente) {
                \Log::warning('Ya existe un crédito para esta venta', ['credito_id' => $creditoExistente->id, 'venta_id' => $data['venta_id']]);
                throw new \Exception('Ya existe un crédito para esta venta');
            }

            \Log::info('Intentando crear el registro en la base de datos...');
            $creditoVenta = CreditoVenta::create($data);
            
            // Verificar que se guardó correctamente
            if (!$creditoVenta->id) {
                \Log::error('Error: El crédito venta no se guardó correctamente - No se generó ID');
                throw new \Exception('No se pudo guardar el crédito venta');
            }

            \Log::info('Registro creado, ID generado:', ['id' => $creditoVenta->id]);

            // Verificar directamente en la base de datos
            $verificacion = \DB::table('credito_ventas')->where('id', $creditoVenta->id)->first();
            \Log::info('Verificación en BD:', ['existe' => $verificacion ? 'SÍ' : 'NO', 'datos' => $verificacion]);

            // Generar las cuotas automáticamente
            $this->generarCuotas($creditoVenta);

            // Cargar relaciones para la respuesta
            $creditoVenta->refresh();
            $creditoVenta->load(['venta', 'cliente', 'cuotas']);

            \Log::info('Crédito venta creado exitosamente:', [
                'id' => $creditoVenta->id,
                'venta_id' => $creditoVenta->venta_id,
                'cliente_id' => $creditoVenta->cliente_id,
                'numero_cuotas' => $creditoVenta->numero_cuotas,
                'total' => $creditoVenta->total,
                'cuotas_generadas' => $creditoVenta->cuotas->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $creditoVenta,
                'message' => 'Crédito venta creado exitosamente'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación al crear crédito venta:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error al crear crédito venta:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear crédito venta: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        // Buscar el crédito directamente por ID para evitar problemas con route model binding
        $creditoVenta = CreditoVenta::with([
            'venta.cliente',
            'venta.detalles.articulo',
            'cliente',
            'cuotas'
        ])->find($id);
        
        if (!$creditoVenta) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado'
            ], 404);
        }
        
        // Si no hay cuotas, generarlas automáticamente
        if ($creditoVenta->cuotas->count() === 0) {
            \Log::info('No hay cuotas, generándolas automáticamente...', ['credito_id' => $creditoVenta->id]);
            $this->generarCuotas($creditoVenta);
            $creditoVenta->refresh();
            $creditoVenta->load('cuotas');
        }
        
        \Log::info('Crédito cargado para mostrar:', [
            'credito_id' => $creditoVenta->id,
            'venta_id' => $creditoVenta->venta_id,
            'tiene_venta' => $creditoVenta->venta ? 'sí' : 'no',
            'tiene_detalles' => $creditoVenta->venta && $creditoVenta->venta->detalles ? 'sí' : 'no',
            'cantidad_detalles' => $creditoVenta->venta && $creditoVenta->venta->detalles ? $creditoVenta->venta->detalles->count() : 0,
            'cantidad_cuotas' => $creditoVenta->cuotas->count()
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $creditoVenta
        ]);
    }

    private function generarCuotas(CreditoVenta $creditoVenta): void
    {
        // Verificar si ya existen cuotas
        $cuotasExistentes = CuotaCredito::where('credito_id', $creditoVenta->id)->count();
        if ($cuotasExistentes > 0) {
            \Log::info('Las cuotas ya existen para este crédito', ['credito_id' => $creditoVenta->id, 'cuotas' => $cuotasExistentes]);
            return;
        }

        $numeroCuotas = $creditoVenta->numero_cuotas;
        $tiempoDiasCuota = $creditoVenta->tiempo_dias_cuota;
        $total = $creditoVenta->total;
        $montoPorCuota = $total / $numeroCuotas;

        // Calcular fecha base (fecha actual + tiempo_dias_cuota)
        $fechaBase = Carbon::now();
        if ($creditoVenta->proximo_pago) {
            $fechaBase = Carbon::parse($creditoVenta->proximo_pago);
        }

        \Log::info('Generando cuotas para crédito', [
            'credito_id' => $creditoVenta->id,
            'numero_cuotas' => $numeroCuotas,
            'tiempo_dias_cuota' => $tiempoDiasCuota,
            'total' => $total,
            'monto_por_cuota' => $montoPorCuota
        ]);

        for ($i = 1; $i <= $numeroCuotas; $i++) {
            $fechaPago = $fechaBase->copy()->addDays(($i - 1) * $tiempoDiasCuota);
            $saldoRestante = $total - ($montoPorCuota * ($i - 1));

            CuotaCredito::create([
                'credito_id' => $creditoVenta->id,
                'numero_cuota' => $i,
                'fecha_pago' => $fechaPago->format('Y-m-d H:i:s'),
                'precio_cuota' => $montoPorCuota,
                'saldo_restante' => $saldoRestante,
                'estado' => 'Pendiente'
            ]);
        }

        \Log::info('Cuotas generadas exitosamente', ['credito_id' => $creditoVenta->id, 'cantidad' => $numeroCuotas]);
    }

    public function update(Request $request, CreditoVenta $creditoVenta)
    {
        $request->validate([
            'venta_id' => 'required|exists:ventas,id|unique:credito_ventas,venta_id,' . $creditoVenta->id,
            'cliente_id' => 'required|exists:clientes,id',
            'numero_cuotas' => 'required|integer|min:1',
            'tiempo_dias_cuota' => 'required|integer|min:1',
            'total' => 'required|numeric|min:0',
            'estado' => 'nullable|string|max:191',
            'proximo_pago' => 'nullable|date',
        ]);

        $creditoVenta->update($request->all());

        return response()->json($creditoVenta);
    }

    public function destroy(CreditoVenta $creditoVenta)
    {
        $creditoVenta->delete();
        return response()->json(null, 204);
    }
}
