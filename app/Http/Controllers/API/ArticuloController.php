<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticuloController extends Controller
{
    public function index()
    {
        $articulos = Articulo::with(['categoria', 'proveedor', 'medida', 'marca', 'industria'])->get();
        return response()->json($articulos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'medida_id' => 'required|exists:medidas,id',
            'marca_id' => 'required|exists:marcas,id',
            'industria_id' => 'required|exists:industrias,id',
            'codigo' => 'required|string|max:255|unique:articulos',
            'nombre' => 'required|string|max:255',
            'unidad_envase' => 'required|integer',
            'precio_costo_unid' => 'required|numeric',
            'precio_costo_paq' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'precio_uno' => 'nullable|numeric',
            'precio_dos' => 'nullable|numeric',
            'precio_tres' => 'nullable|numeric',
            'precio_cuatro' => 'nullable|numeric',
            'stock' => 'required|integer',
            'descripcion' => 'nullable|string|max:256',
            'costo_compra' => 'required|numeric',
            'vencimiento' => 'nullable|integer',
            'fotografia' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('fotografia')) {
            $path = $request->file('fotografia')->store('articulos', 'public');
            $data['fotografia'] = $path;
        }

        $articulo = Articulo::create($data);
        $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

        return response()->json($articulo, 201);
    }

    public function show($id)
    {
        $articulo = Articulo::with(['categoria', 'proveedor', 'medida', 'marca', 'industria'])->find($id);
        
        if (!$articulo) {
            return response()->json([
                'message' => 'Artículo no encontrado',
                'error' => "No se encontró un artículo con el ID: {$id}"
            ], 404);
        }
        
        return response()->json($articulo);
    }

    public function update(Request $request, Articulo $articulo)
    {
        $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'medida_id' => 'required|exists:medidas,id',
            'marca_id' => 'required|exists:marcas,id',
            'industria_id' => 'required|exists:industrias,id',
            'codigo' => 'required|string|max:255|unique:articulos,codigo,' . $articulo->id,
            'nombre' => 'required|string|max:255',
            'unidad_envase' => 'required|integer',
            'precio_costo_unid' => 'required|numeric',
            'precio_costo_paq' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'precio_uno' => 'nullable|numeric',
            'precio_dos' => 'nullable|numeric',
            'precio_tres' => 'nullable|numeric',
            'precio_cuatro' => 'nullable|numeric',
            'stock' => 'required|integer',
            'descripcion' => 'nullable|string|max:256',
            'costo_compra' => 'required|numeric',
            'vencimiento' => 'nullable|integer',
            'fotografia' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('fotografia')) {
            if ($articulo->fotografia) {
                Storage::disk('public')->delete($articulo->fotografia);
            }
            $path = $request->file('fotografia')->store('articulos', 'public');
            $data['fotografia'] = $path;
        }

        $articulo->update($data);
        $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);

        return response()->json($articulo);
    }

    public function destroy(Articulo $articulo)
    {
        if ($articulo->fotografia) {
            Storage::disk('public')->delete($articulo->fotografia);
        }
        $articulo->delete();
        return response()->json(null, 204);
    }
}
