<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::all();
        return response()->json(['data' => $categorias]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'nombre.unique' => 'El nombre ya está en uso. Por favor, elige otro nombre.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede tener más de 255 caracteres.',
            'estado.boolean' => 'El estado debe ser verdadero o falso.',
        ]);

        $categoria = Categoria::create($request->all());

        return response()->json($categoria, 201);
    }

    public function show($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada',
                'error' => "No se encontró una categoría con el ID: {$id}"
            ], 404);
        }

        return response()->json($categoria);
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada',
                'error' => "No se encontró una categoría con el ID: {$id}"
            ], 404);
        }

        $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id . ',id',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'nombre.unique' => 'El nombre ya está en uso. Por favor, elige otro nombre.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede tener más de 255 caracteres.',
            'estado.boolean' => 'El estado debe ser verdadero o falso.',
        ]);

        $categoria->update($request->all());

        return response()->json($categoria);
    }

    public function destroy($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada',
                'error' => "No se encontró una categoría con el ID: {$id}"
            ], 404);
        }

        $categoria->delete();
        return response()->json(null, 204);
    }
}
