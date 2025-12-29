<?php

namespace App\Imports;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Industria;
use App\Models\Marca;
use App\Models\Medida;
use App\Models\Proveedor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    protected ?Collection $categoriasCache = null;
    protected ?Collection $proveedoresCache = null;
    protected ?Collection $marcasCache = null;
    protected ?Collection $medidasCache = null;
    protected ?Collection $industriasCache = null;

    /**
     * Crea un modelo Articulo por cada fila del Excel
     *
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Normalizar el código: convertir números a string, manejar null/vacío
        $codigoRaw = $row['codigo'] ?? null;
        $codigo = null;
        
        if ($codigoRaw !== null && $codigoRaw !== '') {
            // Convertir a string y limpiar
            $codigoTrimmed = trim((string)$codigoRaw);
            if ($codigoTrimmed !== '') {
                $codigo = $codigoTrimmed;
            }
        }

        // TEMPORAL: Para permitir códigos repetidos, NO buscamos artículos existentes
        // Solo usamos $existing para valores por defecto si faltan datos
        $existing = null;
        
        // Si no hay código, buscar por nombre para valores por defecto
        if ($codigo === null && !empty($row['nombre'])) {
            $existing = Articulo::where('nombre', $row['nombre'])->whereNull('codigo')->first();
        }

        $categoriaId = $this->resolveCategoriaId($row['categoria'] ?? null) ?? ($existing ? $existing->categoria_id : null);
        $proveedorId = $this->resolveProveedorId($row['proveedor'] ?? null) ?? ($existing ? $existing->proveedor_id : null);
        $marcaId = $this->resolveMarcaId($row['marca'] ?? null) ?? ($existing ? $existing->marca_id : null);
        $medidaId = $this->resolveMedidaId($row['medida'] ?? null) ?? ($existing ? $existing->medida_id : null);
        $industriaId = $this->resolveIndustriaId($row['industria'] ?? null) ?? ($existing ? $existing->industria_id : null);

        $unidadEnvase = $this->parseInteger($row['unidad_envase'] ?? null) ?? ($existing ? $existing->unidad_envase : 1);
        $precioCostoUnid = $this->parseNumeric($row['precio_costo_unid'] ?? null) ?? ($existing ? $existing->precio_costo_unid : 0);
        $precioCostoPaq = $this->parseNumeric($row['precio_costo_paq'] ?? null) ?? ($existing ? $existing->precio_costo_paq : $precioCostoUnid);
        $precioVenta = $this->parseNumeric($row['precio_venta'] ?? null) ?? ($existing ? $existing->precio_venta : 0);
        $precioUno = $this->parseNumeric($row['precio_uno'] ?? null) ?? ($existing ? $existing->precio_uno : null);
        $precioDos = $this->parseNumeric($row['precio_dos'] ?? null) ?? ($existing ? $existing->precio_dos : null);
        $precioTres = $this->parseNumeric($row['precio_tres'] ?? null) ?? ($existing ? $existing->precio_tres : null);
        $precioCuatro = $this->parseNumeric($row['precio_cuatro'] ?? null) ?? ($existing ? $existing->precio_cuatro : null);
        $stock = $this->parseInteger($row['stock'] ?? null) ?? ($existing ? $existing->stock : 0);
        $costoCompra = $this->parseNumeric($row['costo_compra'] ?? null) ?? ($existing ? $existing->costo_compra : $precioCostoUnid);
        $vencimiento = $this->parseInteger($row['vencimiento'] ?? null) ?? ($existing ? $existing->vencimiento : null);
   
        $estado = $this->parseBoolean($row['estado'] ?? null, $existing ? $existing->estado : true) ? 1 : 0;

        $data = [
            'nombre' => $row['nombre'],
            'codigo' => $codigo, // Agregar el código normalizado (puede ser null)
            'categoria_id' => $categoriaId,
            'proveedor_id' => $proveedorId,
            'medida_id' => $medidaId,
            'marca_id' => $marcaId,
            'industria_id' => $industriaId,
            'unidad_envase' => $unidadEnvase,
            'precio_costo_unid' => $precioCostoUnid,
            'precio_costo_paq' => $precioCostoPaq,
            'precio_venta' => $precioVenta,
            'precio_uno' => $precioUno,
            'precio_dos' => $precioDos,
            'precio_tres' => $precioTres,
            'precio_cuatro' => $precioCuatro,
            'stock' => $stock,
            'descripcion' => $row['descripcion'] ?? ($existing ? $existing->descripcion : null),
            'costo_compra' => $costoCompra,
            'vencimiento' => $vencimiento,
            'estado' => $estado,
        ];

        // TEMPORAL: Permitir códigos repetidos para importación masiva
        // Siempre crear nuevo artículo (permite códigos repetidos)
        // TODO: Revertir después de la importación para usar updateOrCreate
        try {
            // Siempre crear nuevo artículo (permite códigos repetidos temporalmente)
            $articulo = Articulo::create($data);
            
            // Incrementar contador solo si se creó exitosamente
            $this->importedCount++;
        } catch (\Exception $e) {
            \Log::error('Error al crear artículo en importación', [
                'row' => $row,
                'codigo' => $codigo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $articulo;
    }

    /**
     * Reglas de validación para cada fila
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'codigo' => ['nullable',  'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveCategoriaId($value)) {
                    $fail('La categoría especificada no existe.');
                }
            }],
            'proveedor' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveProveedorId($value)) {
                    $fail('El proveedor especificado no existe.');
                }
            }],
            'medida' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveMedidaId($value)) {
                    $fail('La medida especificada no existe.');
                }
            }],
            'marca' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveMarcaId($value)) {
                    $fail('La marca especificada no existe.');
                }
            }],
            'industria' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveIndustriaId($value)) {
                    $fail('La industria especificada no existe.');
                }
            }],
            'unidad_envase' => ['nullable', 'integer', 'min:1'],
            'precio_costo_unid' => ['required', 'numeric', 'min:0'],
            'precio_costo_paq' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'precio_uno' => ['nullable', 'numeric', 'min:0'],
            'precio_dos' => ['nullable', 'numeric', 'min:0'],
            'precio_tres' => ['nullable', 'numeric', 'min:0'],
            'precio_cuatro' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:256'],
            'costo_compra' => ['required', 'numeric', 'min:0'],
            'vencimiento' => ['nullable', 'integer', 'min:0'],
         
            'estado' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->isEstadoText($value)) {
                    $fail('El estado debe ser Activo o Inactivo.');
                }
            }],
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
            'nombre.required' => 'El nombre es obligatorio.',
            'precio_costo_unid.required' => 'El precio de costo por unidad es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'costo_compra.required' => 'El costo de compra es obligatorio.',
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

    protected function resolveCategoriaId(?string $nombre): ?int
    {
        $normalized = $this->normalizeValue($nombre);
        if ($normalized === null) {
            return null;
        }

        if ($this->categoriasCache === null) {
            $this->categoriasCache = Categoria::all();
        }

        $categoria = $this->categoriasCache->first(function (Categoria $categoria) use ($normalized) {
            return $this->normalizeValue($categoria->nombre) === $normalized;
        });

        if ($categoria) {
            return $categoria->id;
        }

        // Crear la categoría si no existe
        $nuevaCategoria = Categoria::create([
            'nombre' => $nombre,
            'estado' => 1,
        ]);
        $this->categoriasCache->push($nuevaCategoria);
        return $nuevaCategoria->id;
    }

    protected function resolveProveedorId(?string $nombre): ?int
    {
        $normalized = $this->normalizeValue($nombre);
        if ($normalized === null) {
            return $this->getDefaultProveedorId();
        }

        if ($this->proveedoresCache === null) {
            $this->proveedoresCache = Proveedor::all();
        }

        $proveedor = $this->proveedoresCache->first(function (Proveedor $proveedor) use ($normalized) {
            return $this->normalizeValue($proveedor->nombre) === $normalized;
        });

        if ($proveedor) {
            return $proveedor->id;
        }

        // Crear el proveedor si no existe
        $nuevoProveedor = Proveedor::create([
            'nombre' => $nombre,
            'estado' => 1,
        ]);
        // Actualizar cache
        $this->proveedoresCache->push($nuevoProveedor);
        return $nuevoProveedor->id;
    }

    protected function resolveMarcaId(?string $nombre): ?int
    {
        $normalized = $this->normalizeValue($nombre);
        if ($normalized === null) {
            return null;
        }

        if ($this->marcasCache === null) {
            $this->marcasCache = Marca::all();
        }

        $marca = $this->marcasCache->first(function (Marca $marca) use ($normalized) {
            $nombrePrincipal = $this->normalizeValue($marca->nombre ?? null);
            $nombreAlterno = $this->normalizeValue($marca->getAttribute('nombre_marca'));

            return $nombrePrincipal === $normalized || ($nombreAlterno !== null && $nombreAlterno === $normalized);
        });

        if ($marca) {
            return $marca->id;
        }

        // Crear la marca si no existe
        $nuevaMarca = Marca::create([
            'nombre' => $nombre,
            'estado' => 1,
        ]);
        // Actualizar cache
        $this->marcasCache->push($nuevaMarca);
        return $nuevaMarca->id;
    }

    protected function resolveMedidaId(?string $nombre): ?int
    {
        $normalized = $this->normalizeValue($nombre);
        if ($normalized === null) {
            return null;
        }

        if ($this->medidasCache === null) {
            $this->medidasCache = Medida::all();
        }

        $medida = $this->medidasCache->first(function (Medida $medida) use ($normalized) {
            return $this->normalizeValue($medida->nombre_medida ?? null) === $normalized;
        });

        if ($medida) {
            return $medida->id;
        }

        // Crear la medida si no existe
        $nuevaMedida = Medida::create([
            'nombre_medida' => $nombre,
            'estado' => 1,
        ]);
        // Actualizar cache
        $this->medidasCache->push($nuevaMedida);
        return $nuevaMedida->id;
    }

    protected function resolveIndustriaId(?string $nombre): ?int
    {
        $normalized = $this->normalizeValue($nombre);
        if ($normalized === null) {
            return null;
        }

        if ($this->industriasCache === null) {
            $this->industriasCache = Industria::all();
        }

        $industria = $this->industriasCache->first(function (Industria $industria) use ($normalized) {
            return $this->normalizeValue($industria->nombre ?? null) === $normalized;
        });

        if ($industria) {
            return $industria->id;
        }

        // Crear la industria si no existe
        $nuevaIndustria = Industria::create([
            'nombre' => $nombre,
            'estado' => 1,
        ]);
        $this->industriasCache->push($nuevaIndustria);
        return $nuevaIndustria->id;
    }

    protected function parseNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,\.\-\s\u{00A0}]/u', '', (string) $value);
        $normalized = str_replace("\u{00A0}", '', $normalized);
        $normalized = str_replace(' ', '', $normalized);

        $lastDot = strrpos($normalized, '.');
        $lastComma = strrpos($normalized, ',');

        if ($lastDot === false && $lastComma === false) {
            $normalized = str_replace(['.', ','], '', $normalized);
        } else {
            $decimalSeparator = null;
            if ($lastDot !== false && $lastComma !== false) {
                $decimalSeparator = $lastDot > $lastComma ? '.' : ',';
            } else {
                $decimalSeparator = $lastDot !== false ? '.' : ',';
            }

            $thousandSeparator = $decimalSeparator === '.' ? ',' : '.';
            $normalized = str_replace($thousandSeparator, '', $normalized);
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $normalized = preg_replace('/[^0-9\-]/', '', (string) $value);

        return is_numeric($normalized) ? (int) $normalized : null;
    }

    protected function parseBoolean($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = $this->normalizeValue((string) $value);

        if ($normalized === null) {
            return $default;
        }

        $truthy = ['1', 'si', 'sí', 'yes', 'true', 'verdadero', 'activo'];
        $falsy = ['0', 'no', 'false', 'falso', 'inactivo'];

        if (in_array($normalized, $truthy, true)) {
            return true;
        }

        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        return $default;
    }

    protected function isBooleanText($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = $this->normalizeValue((string) $value);

        return in_array($normalized, ['1', '0', 'si', 'sí', 'no', 'true', 'false', 'verdadero', 'falso'], true);
    }

    protected function isEstadoText($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = $this->normalizeValue((string) $value);

        return in_array($normalized, ['activo', 'inactivo', '1', '0', 'si', 'sí', 'no'], true);
    }

    protected function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $ascii = Str::ascii($trimmed);
        $lower = mb_strtolower($ascii);

        return in_array($lower, ['n/a', 'na', 'no aplica'], true) ? null : $lower;
    }

    protected function getDefaultProveedorId(): ?int
    {
        if ($this->proveedoresCache === null) {
            $this->proveedoresCache = Proveedor::all();
        }

        $proveedor = $this->proveedoresCache->first();
        return $proveedor ? $proveedor->id : null;
    }
}
