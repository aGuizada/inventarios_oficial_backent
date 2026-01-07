<?php

namespace App\Imports;

use App\Models\Inventario;
use App\Models\Articulo;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Medida;
use App\Models\Marca;
use App\Models\Industria;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class InventarioImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $totalRows = 0;
    protected $errorCount = 0;
    protected $skippedCount = 0;
    protected static $defaultCategoriaId = null;
    protected static $defaultProveedorId = null;
    protected static $defaultMedidaId = null;
    protected static $defaultMarcaId = null;
    protected static $defaultIndustriaId = null;

    public function model(array $row)
    {
        $this->totalRows++;
        $filaNumero = $this->totalRows;
        
        // Normalizar nombres de columnas a minúsculas para evitar problemas con mayúsculas/minúsculas
        $row = array_change_key_case($row, CASE_LOWER);
        
        // Normalizar el código: convertir números a string, manejar null/vacío
        $codigoRaw = $row['codigo_articulo'] ?? null;
        $codigoArticulo = null;
        
        if ($codigoRaw !== null && $codigoRaw !== '') {
            // Convertir a string y limpiar
            $codigoTrimmed = trim((string)$codigoRaw);
            if ($codigoTrimmed !== '') {
                $codigoArticulo = $codigoTrimmed;
            }
        }

        $nombreArticulo = trim($row['nombre_articulo'] ?? 'Sin nombre');
        
        if (empty($nombreArticulo) || $nombreArticulo === 'Sin nombre') {
            // Si no hay nombre, intentar usar el código como nombre
            if ($codigoArticulo) {
                $nombreArticulo = $codigoArticulo;
            } else {
                // Si no hay código ni nombre, omitir esta fila
                \Log::warning("InventarioImport - Fila omitida: sin código ni nombre", [
                    'fila_numero' => $filaNumero,
                    'row' => $row
                ]);
                return null;
            }
        }
        
        $articulo = null;
        $articuloEncontrado = false;
        
        // Si hay código, buscar por código primero (puede haber códigos repetidos, tomar el primero)
        if ($codigoArticulo) {
            $articulo = Articulo::where('codigo', $codigoArticulo)->first();
            if ($articulo) {
                $articuloEncontrado = true;
                \Log::debug("InventarioImport - Artículo encontrado por código", [
                    'codigo' => $codigoArticulo,
                    'nombre' => $nombreArticulo,
                    'articulo_id' => $articulo->id,
                    'fila' => $filaNumero
                ]);
            }
        }
        
        // Si no se encontró por código (o no hay código), buscar por nombre
        if (!$articulo) {
            $articulo = Articulo::where('nombre', $nombreArticulo)->first();
            if ($articulo) {
                $articuloEncontrado = true;
                \Log::debug("InventarioImport - Artículo encontrado por nombre", [
                    'codigo' => $codigoArticulo ?? 'N/A',
                    'nombre' => $nombreArticulo,
                    'articulo_id' => $articulo->id,
                    'fila' => $filaNumero
                ]);
            }
        }
        
        // IMPORTANTE: NO crear artículos nuevos durante la importación de inventario
        // Solo importar inventario para artículos que YA EXISTEN en la tabla articulos
        // Si el artículo no existe, omitir esta fila
        if (!$articulo) {
            $this->skippedCount++;
            \Log::warning("InventarioImport - Artículo no encontrado, omitiendo fila", [
                'codigo' => $codigoArticulo ?? 'N/A',
                'nombre' => $nombreArticulo,
                'fila' => $filaNumero,
                'mensaje' => 'El artículo debe existir en la tabla de artículos antes de importar su inventario'
            ]);
            return null; // Omitir esta fila - el artículo no existe
        }
        
        // Buscar sucursal: si viene en el Excel, buscar por nombre, si no, tomar la de menor id
        $sucursalNombre = $row['sucursal'] ?? null;
        $sucursal = null;
        if ($sucursalNombre && trim($sucursalNombre) !== '') {
            $sucursal = \App\Models\Sucursal::where('nombre', trim($sucursalNombre))->first();
        }
        if (!$sucursal) {
            $sucursal = \App\Models\Sucursal::orderBy('id')->first();
        }

        $almacenNombre = trim($row['almacen'] ?? 'General');
        if (empty($almacenNombre) || $almacenNombre === '') {
            // Si no hay almacén, usar el nombre de la sucursal o 'General'
            $almacenNombre = $sucursal ? $sucursal->nombre : 'General';
        }

        try {
            $almacen = Almacen::firstOrCreate(
                [
                    'nombre_almacen' => $almacenNombre,
                    'sucursal_id' => $sucursal ? $sucursal->id : null,
                ],
                [
                    'descripcion' => 'Almacén creado por importación'
                ]
            );
        } catch (\Exception $e) {
            \Log::error("InventarioImport - Error al crear/buscar almacén", [
                'almacen_nombre' => $almacenNombre,
                'sucursal_id' => $sucursal ? $sucursal->id : null,
                'error' => $e->getMessage(),
                'fila' => $this->totalRows
            ]);
            throw $e;
        }

        // Normalizar cantidad y saldo_stock (pueden venir como string, número o vacío)
        $saldoStock = $this->parseNumeric($row['saldo_stock'] ?? null) ?? 0;
        $cantidad = $this->parseNumeric($row['cantidad'] ?? null) ?? 0;
        
        // Si cantidad está vacía, usar saldo_stock como cantidad
        if ($cantidad == 0 && $saldoStock > 0) {
            $cantidad = $saldoStock;
        }
        
        // Si ambos son 0, usar 0 (producto agotado pero se importa igual)
        if ($cantidad == 0 && $saldoStock == 0) {
            $cantidad = 0;
            $saldoStock = 0;
        }
        
        // IMPORTANTE: Importar TODOS los productos, incluso con stock 0
        // Los productos con stock 0 aparecerán como "agotado" pero se importan igual

        // IMPORTANTE: Cada fila del Excel crea un registro de inventario INDEPENDIENTE
        // Los stocks son independientes - cada producto importado tiene su propio registro
        // NO se suman stocks, cada fila crea un nuevo inventario (incluso si el producto, almacén y fecha son iguales)
        $fechaVencimiento = $this->parseDate($row['fecha_vencimiento'] ?? null) ?? '2099-01-01';
        
        try {
            // SIEMPRE crear un nuevo registro de inventario (independiente, sin importar si ya existe uno)
            // Esto permite tener múltiples registros del mismo producto con diferentes stocks
            $inventario = Inventario::create([
                'articulo_id' => $articulo->id,
                'almacen_id' => $almacen->id,
                'fecha_vencimiento' => $fechaVencimiento,
                'saldo_stock' => $saldoStock,
                'cantidad' => $cantidad,
            ]);
            
            // Verificar que se creó correctamente
            if (!$inventario || !$inventario->id) {
                throw new \Exception("No se pudo crear el inventario - el objeto retornado es nulo o no tiene ID");
            }
            
            \Log::info("InventarioImport - Nuevo inventario creado exitosamente", [
                'inventario_id' => $inventario->id,
                'articulo_id' => $articulo->id,
                'articulo_nombre' => $articulo->nombre,
                'codigo' => $codigoArticulo ?? 'N/A',
                'almacen_id' => $almacen->id,
                'almacen_nombre' => $almacen->nombre_almacen ?? 'N/A',
                'saldo_stock' => $saldoStock,
                'cantidad' => $cantidad,
                'fecha_vencimiento' => $fechaVencimiento,
                'fila' => $filaNumero,
                'imported_count' => $this->importedCount + 1
            ]);

            $this->importedCount++;
            return $inventario;
        } catch (\Exception $e) {
            $this->errorCount++;
            \Log::error("InventarioImport - Error al crear inventario", [
                'articulo_id' => $articulo->id ?? 'N/A',
                'articulo_nombre' => $articulo->nombre ?? 'N/A',
                'almacen_id' => $almacen->id ?? 'N/A',
                'codigo' => $codigoArticulo ?? 'N/A',
                'nombre' => $nombreArticulo ?? 'N/A',
                'saldo_stock' => $saldoStock,
                'cantidad' => $cantidad,
                'fecha_vencimiento' => $fechaVencimiento,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'fila' => $filaNumero
            ]);
            // Re-lanzar la excepción para que SkipsOnError la maneje
            throw $e;
        }
    }

    /**
     * Parse numeric value from Excel (can be string, number, or empty)
     */
    private function parseNumeric($value): ?float
    {
        if ($value === null || $value === '' || $value === ' ') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        // Intentar limpiar y convertir
        $cleaned = trim((string)$value);
        if ($cleaned === '' || $cleaned === '-') {
            return null;
        }
        
        // Remover caracteres no numéricos excepto punto y coma
        $cleaned = preg_replace('/[^0-9.,-]/', '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);
        
        if (is_numeric($cleaned)) {
            return (float)$cleaned;
        }
        
        return null;
    }

    /**
     * Parse date value from Excel
     */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '' || $value === ' ') {
            return null;
        }
        
        // Si ya es una fecha válida en formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Intentar parsear como fecha de Excel (número de días desde 1900)
        if (is_numeric($value)) {
            try {
                // Usar PhpSpreadsheet si está disponible
                if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                    return $date->format('Y-m-d');
                } else {
                    // Alternativa: calcular días desde 1900-01-01
                    // Excel cuenta desde 1900-01-01 pero tiene un bug (considera 1900 como año bisiesto)
                    $baseDate = new \DateTime('1899-12-30');
                    $baseDate->modify('+' . (int)$value . ' days');
                    return $baseDate->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // Si falla, intentar parsear como timestamp
                if ($value > 0 && $value < 100000) {
                    $baseDate = new \DateTime('1899-12-30');
                    $baseDate->modify('+' . (int)$value . ' days');
                    return $baseDate->format('Y-m-d');
                }
            }
        }
        
        // Intentar parsear como string de fecha
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'codigo_articulo' => ['nullable'], // Código opcional, puede ser string o número
            'nombre_articulo' => ['required'], // Nombre es obligatorio para identificar el artículo
            'sucursal' => ['nullable'],
            'almacen' => ['required'],
            'saldo_stock' => ['nullable', 'numeric'], // Ahora es nullable
            'cantidad' => ['nullable', 'numeric'], // Ahora es nullable, se usará saldo_stock si está vacío
            'fecha_vencimiento' => ['nullable'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nombre_articulo.required' => 'El nombre del artículo es obligatorio.',
            'almacen.required' => 'El almacén es obligatorio.',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        // Contar tanto los failures de validación como las filas omitidas manualmente (stock 0, sin código/nombre, etc.)
        return count($this->failures()) + $this->skippedCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Método llamado cuando hay un error al procesar una fila
     */
    public function onError(\Throwable $e)
    {
        $this->errorCount++;
        \Log::error("InventarioImport - Error al procesar fila", [
            'fila_numero' => $this->totalRows,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Obtener el ID de la categoría por defecto (primera categoría disponible)
     */
    protected function getDefaultCategoriaId(): int
    {
        if (self::$defaultCategoriaId === null) {
            $categoria = Categoria::orderBy('id')->first();
            if (!$categoria) {
                // Si no hay categorías, crear una por defecto
                $categoria = Categoria::create([
                    'nombre' => 'General',
                    'descripcion' => 'Categoría por defecto para importación'
                ]);
            }
            self::$defaultCategoriaId = $categoria->id;
        }
        return self::$defaultCategoriaId;
    }

    /**
     * Obtener el ID del proveedor por defecto (primer proveedor disponible)
     */
    protected function getDefaultProveedorId(): int
    {
        if (self::$defaultProveedorId === null) {
            $proveedor = Proveedor::orderBy('id')->first();
            if (!$proveedor) {
                // Si no hay proveedores, crear uno por defecto
                $proveedor = Proveedor::create([
                    'nombre' => 'Proveedor General',
                    'contacto' => 'N/A',
                    'telefono' => 'N/A',
                    'email' => 'n/a@example.com'
                ]);
            }
            self::$defaultProveedorId = $proveedor->id;
        }
        return self::$defaultProveedorId;
    }

    /**
     * Obtener el ID de la medida por defecto (primera medida disponible)
     */
    protected function getDefaultMedidaId(): int
    {
        if (self::$defaultMedidaId === null) {
            $medida = Medida::orderBy('id')->first();
            if (!$medida) {
                // Si no hay medidas, crear una por defecto
                $medida = Medida::create([
                    'nombre' => 'Unidad',
                    'abreviatura' => 'UND'
                ]);
            }
            self::$defaultMedidaId = $medida->id;
        }
        return self::$defaultMedidaId;
    }

    /**
     * Obtener el ID de la marca por defecto (primera marca disponible)
     */
    protected function getDefaultMarcaId(): int
    {
        if (self::$defaultMarcaId === null) {
            $marca = Marca::orderBy('id')->first();
            if (!$marca) {
                // Si no hay marcas, crear una por defecto
                $marca = Marca::create([
                    'nombre' => 'Genérica',
                    'descripcion' => 'Marca por defecto para importación'
                ]);
            }
            self::$defaultMarcaId = $marca->id;
        }
        return self::$defaultMarcaId;
    }

    /**
     * Obtener el ID de la industria por defecto (primera industria disponible)
     */
    protected function getDefaultIndustriaId(): int
    {
        if (self::$defaultIndustriaId === null) {
            $industria = Industria::orderBy('id')->first();
            if (!$industria) {
                // Si no hay industrias, crear una por defecto
                $industria = Industria::create([
                    'nombre' => 'General',
                    'descripcion' => 'Industria por defecto para importación'
                ]);
            }
            self::$defaultIndustriaId = $industria->id;
        }
        return self::$defaultIndustriaId;
    }
}
