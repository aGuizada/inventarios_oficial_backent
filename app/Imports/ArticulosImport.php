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
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class ArticulosImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithEvents
{
    use SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errorCount = 0;
    protected $totalRows = 0;
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
        // Incrementar contador de filas procesadas (incluyendo vacías)
        $this->totalRows++;
        
        // Leer código y nombre directamente del Excel
        // El código puede ser null - no es obligatorio
        $nombreRaw = trim($row['nombre'] ?? '');
        $codigoRaw = trim($row['codigo'] ?? '');
        
        // Si la fila está completamente vacía (sin nombre ni código ni otros datos), omitirla
        $tieneDatos = false;
        foreach ($row as $key => $value) {
            if (!empty(trim($value ?? ''))) {
                $tieneDatos = true;
                break;
            }
        }
        
        if (!$tieneDatos) {
            \Log::debug('Fila completamente vacía omitida', [
                'fila_numero' => $this->totalRows,
                'total_filas_procesadas' => $this->totalRows
            ]);
            return null; // Omitir solo filas completamente vacías
        }
        
        \Log::debug('Procesando fila', [
            'fila_numero' => $this->totalRows,
            'nombre' => $nombreRaw ?: 'N/A',
            'codigo' => $codigoRaw ?: 'N/A (null permitido)',
            'total_procesadas' => $this->totalRows
        ]);
        
        // El código puede ser null - no es obligatorio
        // Si no hay nombre, usar código como nombre o generar uno por defecto
        $nombreArticulo = $nombreRaw;
        if (empty($nombreArticulo)) {
            // Si no hay nombre, intentar usar el código como nombre
            if ($codigoRaw !== null && $codigoRaw !== '') {
                $nombreArticulo = trim((string)$codigoRaw);
            }
            
            // Si aún no hay nombre, usar un nombre por defecto
            if (empty($nombreArticulo)) {
                $nombreArticulo = 'Producto sin nombre ' . $this->totalRows;
            }
        }
        
        // Normalizar el código: puede ser null, no es obligatorio
        // Si existe, limitarlo a 255 caracteres máximo
        $codigo = null;
        
        if ($codigoRaw !== null && $codigoRaw !== '') {
            $codigoTrimmed = trim((string)$codigoRaw);
            if ($codigoTrimmed !== '') {
                // Limitar a 255 caracteres máximo (límite de la base de datos)
                $codigo = mb_substr($codigoTrimmed, 0, 255);
                
                // Log si se truncó el código
                if (mb_strlen($codigoTrimmed) > 255) {
                    \Log::warning("Código truncado de " . mb_strlen($codigoTrimmed) . " a 255 caracteres", [
                        'codigo_original' => $codigoTrimmed,
                        'codigo_truncado' => $codigo,
                        'fila' => $this->totalRows
                    ]);
                }
            }
        }
        
        // El código puede ser null - esto es válido y permitido

        // IMPORTANTE: No verificamos duplicados - cada fila del Excel es un producto independiente
        // Esto permite importar todos los productos, incluso si tienen el mismo código y nombre
        // El usuario quiere importar todos los productos del Excel sin omitir ninguno

        // Resolver IDs con valores por defecto si no existen
        // Si no se puede resolver, crear valores por defecto o usar el primero disponible
        try {
            $categoriaId = $this->resolveCategoriaId($row['categoria'] ?? null);
            if (!$categoriaId) {
                $categoriaId = $this->getDefaultCategoriaId();
                if (!$categoriaId) {
                    // Crear una categoría por defecto si no existe ninguna
                    try {
                        $categoriaDefault = Categoria::firstOrCreate(['nombre' => 'General'], ['descripcion' => 'Categoría por defecto', 'estado' => 1]);
                        $categoriaId = $categoriaDefault->id;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear categoría por defecto: ' . $e->getMessage());
                        // Usar la primera categoría disponible o crear una nueva
                        $categoria = Categoria::first();
                        $categoriaId = $categoria ? $categoria->id : Categoria::create(['nombre' => 'General', 'descripcion' => 'Categoría por defecto', 'estado' => 1])->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al resolver categoría: ' . $e->getMessage());
            $categoria = Categoria::first();
            $categoriaId = $categoria ? $categoria->id : Categoria::create(['nombre' => 'General', 'descripcion' => 'Categoría por defecto', 'estado' => 1])->id;
        }
        
        try {
            $proveedorId = $this->resolveProveedorId($row['proveedor'] ?? null);
            if (!$proveedorId) {
                $proveedorId = $this->getDefaultProveedorId();
                if (!$proveedorId) {
                    // Crear un proveedor por defecto si no existe ninguno
                    try {
                        $proveedorDefault = Proveedor::firstOrCreate(['nombre' => 'General'], ['estado' => 1]);
                        $proveedorId = $proveedorDefault->id;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear proveedor por defecto: ' . $e->getMessage());
                        $proveedor = Proveedor::first();
                        $proveedorId = $proveedor ? $proveedor->id : Proveedor::create(['nombre' => 'General', 'estado' => 1])->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al resolver proveedor: ' . $e->getMessage());
            $proveedor = Proveedor::first();
            $proveedorId = $proveedor ? $proveedor->id : Proveedor::create(['nombre' => 'General', 'estado' => 1])->id;
        }
        
        try {
            $marcaId = $this->resolveMarcaId($row['marca'] ?? null);
            if (!$marcaId) {
                $marcaId = $this->getDefaultMarcaId();
                if (!$marcaId) {
                    // Crear una marca por defecto si no existe ninguna
                    try {
                        $marcaDefault = Marca::firstOrCreate(['nombre' => 'General'], ['estado' => 1]);
                        $marcaId = $marcaDefault->id;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear marca por defecto: ' . $e->getMessage());
                        $marca = Marca::first();
                        $marcaId = $marca ? $marca->id : Marca::create(['nombre' => 'General', 'estado' => 1])->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al resolver marca: ' . $e->getMessage());
            $marca = Marca::first();
            $marcaId = $marca ? $marca->id : Marca::create(['nombre' => 'General', 'estado' => 1])->id;
        }
        
        try {
            $medidaId = $this->resolveMedidaId($row['medida'] ?? null);
            if (!$medidaId) {
                $medidaId = $this->getDefaultMedidaId();
                if (!$medidaId) {
                    // Crear una medida por defecto si no existe ninguna
                    try {
                        $medidaDefault = Medida::firstOrCreate(['nombre_medida' => 'Unidad'], ['estado' => 1]);
                        $medidaId = $medidaDefault->id;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear medida por defecto: ' . $e->getMessage());
                        $medida = Medida::first();
                        $medidaId = $medida ? $medida->id : Medida::create(['nombre_medida' => 'Unidad', 'estado' => 1])->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al resolver medida: ' . $e->getMessage());
            $medida = Medida::first();
            $medidaId = $medida ? $medida->id : Medida::create(['nombre_medida' => 'Unidad', 'estado' => 1])->id;
        }
        
        try {
            $industriaId = $this->resolveIndustriaId($row['industria'] ?? null);
            if (!$industriaId) {
                $industriaId = $this->getDefaultIndustriaId();
                if (!$industriaId) {
                    // Crear una industria por defecto si no existe ninguna
                    try {
                        $industriaDefault = Industria::firstOrCreate(['nombre' => 'General'], ['estado' => 1]);
                        $industriaId = $industriaDefault->id;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear industria por defecto: ' . $e->getMessage());
                        $industria = Industria::first();
                        $industriaId = $industria ? $industria->id : Industria::create(['nombre' => 'General', 'estado' => 1])->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al resolver industria: ' . $e->getMessage());
            $industria = Industria::first();
            $industriaId = $industria ? $industria->id : Industria::create(['nombre' => 'General', 'estado' => 1])->id;
        }

        // Usar valores por defecto si no se proporcionan (0 para precios, 1 para unidad_envase)
        $unidadEnvase = $this->parseInteger($row['unidad_envase'] ?? null) ?? 1;
        $precioCostoUnid = $this->parseNumeric($row['precio_costo_unid'] ?? null) ?? 0;
        $precioCostoPaq = $this->parseNumeric($row['precio_costo_paq'] ?? null) ?? $precioCostoUnid;
        $precioVenta = $this->parseNumeric($row['precio_venta'] ?? null) ?? 0;
        $precioUno = $this->parseNumeric($row['precio_uno'] ?? null) ?? null;
        $precioDos = $this->parseNumeric($row['precio_dos'] ?? null) ?? null;
        $precioTres = $this->parseNumeric($row['precio_tres'] ?? null) ?? null;
        $precioCuatro = $this->parseNumeric($row['precio_cuatro'] ?? null) ?? null;
        $stock = $this->parseInteger($row['stock'] ?? null) ?? 0;
        $costoCompra = $this->parseNumeric($row['costo_compra'] ?? null) ?? $precioCostoUnid;
        $vencimiento = $this->parseInteger($row['vencimiento'] ?? null) ?? null;
   
        $estado = $this->parseBoolean($row['estado'] ?? null, true) ? 1 : 0;

        $data = [
            'nombre' => $nombreArticulo,
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
            'descripcion' => $row['descripcion'] ?? null,
            'costo_compra' => $costoCompra,
            'vencimiento' => $vencimiento,
            'estado' => $estado,
        ];

        // IMPORTANTE: Siempre crear nuevo artículo, sin importar si el nombre se repite
        // Cada fila del Excel es un producto independiente
        try {
            // Asegurar que todos los campos requeridos tengan valores válidos
            // Si algún campo es null, usar valores por defecto en lugar de omitir el producto
            if ($categoriaId === null) {
                \Log::warning('Categoría es null, usando valor por defecto', ['fila' => $this->totalRows]);
                $categoria = Categoria::first();
                $categoriaId = $categoria ? $categoria->id : Categoria::create(['nombre' => 'General', 'descripcion' => 'Categoría por defecto', 'estado' => 1])->id;
            }
            if ($proveedorId === null) {
                \Log::warning('Proveedor es null, usando valor por defecto', ['fila' => $this->totalRows]);
                $proveedor = Proveedor::first();
                $proveedorId = $proveedor ? $proveedor->id : Proveedor::create(['nombre' => 'General', 'estado' => 1])->id;
            }
            if ($marcaId === null) {
                \Log::warning('Marca es null, usando valor por defecto', ['fila' => $this->totalRows]);
                $marca = Marca::first();
                $marcaId = $marca ? $marca->id : Marca::create(['nombre' => 'General', 'estado' => 1])->id;
            }
            if ($medidaId === null) {
                \Log::warning('Medida es null, usando valor por defecto', ['fila' => $this->totalRows]);
                $medida = Medida::first();
                $medidaId = $medida ? $medida->id : Medida::create(['nombre_medida' => 'Unidad', 'estado' => 1])->id;
            }
            if ($industriaId === null) {
                \Log::warning('Industria es null, usando valor por defecto', ['fila' => $this->totalRows]);
                $industria = Industria::first();
                $industriaId = $industria ? $industria->id : Industria::create(['nombre' => 'General', 'estado' => 1])->id;
            }
            
            // Actualizar el array $data con los IDs corregidos
            $data['categoria_id'] = $categoriaId;
            $data['proveedor_id'] = $proveedorId;
            $data['marca_id'] = $marcaId;
            $data['medida_id'] = $medidaId;
            $data['industria_id'] = $industriaId;
            
            $articulo = Articulo::create($data);
            
            // Incrementar contador solo si se creó exitosamente
            $this->importedCount++;
            
            \Log::info('Artículo creado en importación', [
                'fila' => $this->totalRows,
                'codigo' => $codigo ?? 'N/A',
                'nombre' => $nombreArticulo,
                'articulo_id' => $articulo->id,
                'total_creados' => $this->importedCount,
                'total_omitidos' => $this->skippedCount
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->errorCount++;
            \Log::error('Error de base de datos al crear artículo', [
                'codigo' => $codigo,
                'nombre' => $nombreArticulo,
                'data' => $data,
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A'
            ]);
            // No lanzar excepción para que continúe con los siguientes productos
            return null;
        } catch (\Exception $e) {
            $this->errorCount++;
            \Log::error('Error al crear artículo en importación', [
                'row' => $row,
                'codigo' => $codigo,
                'nombre' => $nombreArticulo,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para que continúe con los siguientes productos
            // El error será registrado pero no detendrá la importación
            return null;
        }

        return $articulo;
    }

    /**
     * Reglas de validación para cada fila
     * IMPORTANTE: Todas las validaciones son opcionales para permitir importar todos los productos
     * Los valores por defecto se asignan en el método model()
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'max:255'],
            'nombre' => ['nullable', 'string', 'max:255'], // Cambiado a nullable - si no hay nombre, se usa código o nombre por defecto
            'categoria' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveCategoriaId($value)) {
                    // No fallar, solo crear la categoría si no existe
                }
            }],
            'proveedor' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveProveedorId($value)) {
                    // No fallar, usar proveedor por defecto
                }
            }],
            'medida' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveMedidaId($value)) {
                    // No fallar, usar medida por defecto
                }
            }],
            'marca' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveMarcaId($value)) {
                    // No fallar, usar marca por defecto
                }
            }],
            'industria' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->resolveIndustriaId($value)) {
                    // No fallar, usar industria por defecto
                }
            }],
            'unidad_envase' => ['nullable', 'integer', 'min:1'],
            'precio_costo_unid' => ['nullable', 'numeric', 'min:0'], // Cambiado a nullable
            'precio_costo_paq' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'], // Cambiado a nullable
            'precio_uno' => ['nullable', 'numeric', 'min:0'],
            'precio_dos' => ['nullable', 'numeric', 'min:0'],
            'precio_tres' => ['nullable', 'numeric', 'min:0'],
            'precio_cuatro' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:256'],
            'costo_compra' => ['nullable', 'numeric', 'min:0'], // Cambiado a nullable
            'vencimiento' => ['nullable', 'integer', 'min:0'],
         
            'estado' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($this->normalizeValue($value) !== null && !$this->isEstadoText($value)) {
                    // No fallar, usar estado por defecto (activo)
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
        return $this->skippedCount + count($this->failures());
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function onError(\Throwable $e): void
    {
        $this->errorCount++;
        \Log::error('Error en importación de artículo (onError)', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures): void
    {
        $this->skippedCount += count($failures);
        foreach ($failures as $failure) {
            \Log::warning('Fallo en validación de artículo', [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values()
            ]);
        }
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

        // Remover caracteres no numéricos excepto punto, coma, guion y espacios
        // Usar una expresión regular simple sin caracteres Unicode para compatibilidad con PCRE2
        $normalized = preg_replace('/[^0-9,\.\-\s]/', '', (string) $value);
        // Remover espacios no separadores (NBSP) y otros espacios
        $normalized = str_replace(["\xC2\xA0", "\xA0", ' '], '', $normalized);
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

    protected function getDefaultCategoriaId(): ?int
    {
        if ($this->categoriasCache === null) {
            $this->categoriasCache = Categoria::all();
        }

        $categoria = $this->categoriasCache->first();
        return $categoria ? $categoria->id : null;
    }

    protected function getDefaultMarcaId(): ?int
    {
        if ($this->marcasCache === null) {
            $this->marcasCache = Marca::all();
        }

        $marca = $this->marcasCache->first();
        return $marca ? $marca->id : null;
    }

    protected function getDefaultMedidaId(): ?int
    {
        if ($this->medidasCache === null) {
            $this->medidasCache = Medida::all();
        }

        $medida = $this->medidasCache->first();
        return $medida ? $medida->id : null;
    }

    protected function getDefaultIndustriaId(): ?int
    {
        if ($this->industriasCache === null) {
            $this->industriasCache = Industria::all();
        }

        $industria = $this->industriasCache->first();
        return $industria ? $industria->id : null;
    }

    /**
     * Registra eventos para la importación
     */
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function(AfterImport $event) {
                \Log::info('=== RESUMEN DE IMPORTACIÓN DE ARTÍCULOS ===', [
                    'total_filas_procesadas' => $this->totalRows,
                    'articulos_creados' => $this->importedCount,
                    'articulos_omitidos_duplicados' => $this->skippedCount,
                    'errores' => $this->errorCount,
                    'total_articulos_en_bd' => \App\Models\Articulo::count()
                ]);
            },
        ];
    }
}
