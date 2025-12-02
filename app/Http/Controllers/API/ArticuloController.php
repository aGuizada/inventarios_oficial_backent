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
            'marca_id' => 'nullable|exists:marcas,id',
            'industria_id' => 'nullable|exists:industrias,id',
            'codigo' => 'required|string|max:50|unique:articulos',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio_compra' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'stock_minimo' => 'nullable|integer',
            'stock_maximo' => 'nullable|integer',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('articulos', 'public');
            $data['foto'] = $path;
        }

        $articulo = Articulo::create($data);

        return response()->json($articulo, 201);
    }

    public function show(Articulo $articulo)
    {
        $articulo->load(['categoria', 'proveedor', 'medida', 'marca', 'industria']);
        return response()->json($articulo);
    }

    public function update(Request $request, Articulo $articulo)
    {
        $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'medida_id' => 'required|exists:medidas,id',
            'marca_id' => 'nullable|exists:marcas,id',
            'industria_id' => 'nullable|exists:industrias,id',
            'codigo' => 'required|string|max:50|unique:articulos,codigo,' . $articulo->id,
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio_compra' => 'required|numeric',
            'precio_venta' => 'required|numeric',
            'stock_minimo' => 'nullable|integer',
            'stock_maximo' => 'nullable|integer',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('foto')) {
            if ($articulo->foto) {
                Storage::disk('public')->delete($articulo->foto);
            }
            $path = $request->file('foto')->store('articulos', 'public');
            $data['foto'] = $path;
        }

        $articulo->update($data);

        return response()->json($articulo);
    }

    public function destroy(Articulo $articulo)
    {
        if ($articulo->foto) {
            Storage::disk('public')->delete($articulo->foto);
        }
        $articulo->delete();
        return response()->json(null, 204);
    }
}
