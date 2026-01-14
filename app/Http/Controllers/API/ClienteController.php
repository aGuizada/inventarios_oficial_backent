<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Exports\ClientesExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ClienteController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = Cliente::query();

        // Campos buscables: nombre, teléfono, email, número de documento, dirección
        $searchableFields = [
            'nombre',
            'telefono',
            'email',
            'num_documento',
            'direccion'
        ];

        // Aplicar búsqueda
        $query = $this->applySearch($query, $request, $searchableFields);

        // Campos ordenables
        $sortableFields = ['id', 'nombre', 'email', 'telefono', 'created_at'];

        // Aplicar ordenamiento
        $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');

        // Aplicar paginación
        return $this->paginateResponse($query, $request, 15, 100);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_cliente' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        $cliente = Cliente::create($request->all());

        return response()->json($cliente, 201);
    }

    public function show(Cliente $cliente)
    {
        return response()->json($cliente);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nit' => 'nullable|string|max:20',
            'tipo_cliente' => 'nullable|string|max:50',
            'estado' => 'boolean',
        ]);

        // IMPORTANTE: Solo actualizar campos que se enviaron explícitamente
        // Esto preserva los datos existentes del servidor que no se están actualizando
        $camposPermitidos = ['nombre', 'telefono', 'direccion', 'email', 'nit', 'tipo_cliente', 'estado'];
        $data = $request->only($camposPermitidos);

        $cliente->update($data);

        return response()->json($cliente);
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return response()->json(null, 204);
    }

    /**
     * Toggle client status (active/inactive)
     */
    public function toggleStatus(Cliente $cliente)
    {
        $cliente->estado = !$cliente->estado;
        $cliente->save();

        return response()->json([
            'success' => true,
            'message' => $cliente->estado ? 'Cliente activado' : 'Cliente desactivado',
            'data' => $cliente
        ]);
    }
    /**
     * Exporta clientes a Excel
     */
    public function exportExcel()
    {
        return Excel::download(new ClientesExport, 'clientes.xlsx');
    }

    /**
     * Exporta clientes a PDF
     */
    public function exportPDF()
    {
        $clientes = Cliente::all();
        $pdf = Pdf::loadView('pdf.clientes', compact('clientes'));
        return $pdf->download('clientes.pdf');
    }
}
