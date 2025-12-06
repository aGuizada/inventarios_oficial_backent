<?php

namespace App\Imports;

use App\Models\Articulo;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ArticulosImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $importedCount = 0;

    /**
     * Crea un modelo Articulo por cada fila del Excel
     *
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->importedCount++;

        return new Articulo([
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'categoria_id' => $row['categoria_id'],
            'proveedor_id' => $row['proveedor_id'],
            'medida_id' => $row['medida_id'],
            'marca_id' => $row['marca_id'],
            'industria_id' => $row['industria_id'],
            'unidad_envase' => $row['unidad_envase'],
            'precio_costo_unid' => $row['precio_costo_unid'],
            'precio_costo_paq' => $row['precio_costo_paq'],
            'precio_venta' => $row['precio_venta'],
            'precio_uno' => $row['precio_uno'] ?? null,
            'precio_dos' => $row['precio_dos'] ?? null,
            'precio_tres' => $row['precio_tres'] ?? null,
            'precio_cuatro' => $row['precio_cuatro'] ?? null,
            'stock' => $row['stock'],
            'descripcion' => $row['descripcion'] ?? null,
            'costo_compra' => $row['costo_compra'],
            'vencimiento' => $row['vencimiento'] ?? null,
            'estado' => $row['estado'] ?? 1,
        ]);
    }

    /**
     * Reglas de validación para cada fila
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'codigo' => 'required|string|max:255|unique:articulos,codigo',
            'nombre' => 'required|string|max:255',
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'medida_id' => 'required|exists:medidas,id',
            'marca_id' => 'required|exists:marcas,id',
            'industria_id' => 'required|exists:industrias,id',
            'unidad_envase' => 'required|integer|min:1',
            'precio_costo_unid' => 'required|numeric|min:0',
            'precio_costo_paq' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'precio_uno' => 'nullable|numeric|min:0',
            'precio_dos' => 'nullable|numeric|min:0',
            'precio_tres' => 'nullable|numeric|min:0',
            'precio_cuatro' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'descripcion' => 'nullable|string|max:256',
            'costo_compra' => 'required|numeric|min:0',
            'vencimiento' => 'nullable|integer|min:0',
            'estado' => 'required|boolean',
        ];
    }

    /**
     * Mensajes de validación personalizados
     *
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'El código ya existe en el sistema.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría especificada no existe.',
            'proveedor_id.required' => 'El proveedor es obligatorio.',
            'proveedor_id.exists' => 'El proveedor especificado no existe.',
            'medida_id.required' => 'La medida es obligatoria.',
            'medida_id.exists' => 'La medida especificada no existe.',
            'marca_id.required' => 'La marca es obligatoria.',
            'marca_id.exists' => 'La marca especificada no existe.',
            'industria_id.required' => 'La industria es obligatoria.',
            'industria_id.exists' => 'La industria especificada no existe.',
            'unidad_envase.required' => 'La unidad de envase es obligatoria.',
            'precio_costo_unid.required' => 'El precio de costo por unidad es obligatorio.',
            'precio_costo_paq.required' => 'El precio de costo por paquete es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'stock.required' => 'El stock es obligatorio.',
            'costo_compra.required' => 'El costo de compra es obligatorio.',
            'estado.required' => 'El estado es obligatorio.',
        ];
    }

    /**
     * Retorna el número de artículos importados exitosamente
     *
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Retorna el número de filas omitidas
     *
     * @return int
     */
    public function getSkippedCount(): int
    {
        return count($this->failures());
    }
}
