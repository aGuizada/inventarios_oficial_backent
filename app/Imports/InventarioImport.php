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
    protected static $defaultCategoriaId = null;
    protected static $defaultProveedorId = null;
    protected static $defaultMedidaId = null;
    protected static $defaultMarcaId = null;
    protected static $defaultIndustriaId = null;

    public function model(array $row)
    {
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
        
        if (empty($nombreArticulo)) {
            throw new \Exception('El nombre del artículo es obligatorio');
        }
        
        $articulo = null;
        
        // Si hay código, buscar por código primero
        if ($codigoArticulo) {
            $articulo = Articulo::where('codigo', $codigoArticulo)->first();
        }
        
        // Si no se encontró por código (o no hay código), buscar por nombre
        if (!$articulo) {
            $articulo = Articulo::where('nombre', $nombreArticulo)->first();
        }
        
        // Si no existe, crear uno nuevo
        if (!$articulo) {
            $articulo = Articulo::create([
                'codigo' => $codigoArticulo,
                'nombre' => $nombreArticulo,
                'categoria_id' => $this->getDefaultCategoriaId(),
                'proveedor_id' => $this->getDefaultProveedorId(),
                'medida_id' => $this->getDefaultMedidaId(),
                'marca_id' => $this->getDefaultMarcaId(),
                'industria_id' => $this->getDefaultIndustriaId(),
                'unidad_envase' => 1,
                'precio_costo_unid' => 0,
                'precio_costo_paq' => 0,
                'precio_venta' => 0,
                'precio_uno' => null,
                'precio_dos' => null,
                'precio_tres' => null,
                'precio_cuatro' => null,
                'stock' => 0,
                'descripcion' => null,
                'costo_compra' => 0, // Campo requerido
                'vencimiento' => null,
                'estado' => 1
            ]);
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
        if (empty($almacenNombre)) {
            $almacenNombre = 'General';
        }

        $almacen = Almacen::firstOrCreate([
            'nombre_almacen' => $almacenNombre,
            'sucursal_id' => $sucursal ? $sucursal->id : null,
        ]);

        // Normalizar cantidad y saldo_stock (pueden venir como string, número o vacío)
        $saldoStock = $this->parseNumeric($row['saldo_stock'] ?? null) ?? 0;
        $cantidad = $this->parseNumeric($row['cantidad'] ?? null) ?? 0;
        
        // Si cantidad está vacía, usar saldo_stock como cantidad
        if ($cantidad == 0 && $saldoStock > 0) {
            $cantidad = $saldoStock;
        }

        $inventario = Inventario::updateOrCreate(
            [
                'articulo_id' => $articulo->id,
                'almacen_id' => $almacen->id,
                'fecha_vencimiento' => $this->parseDate($row['fecha_vencimiento'] ?? null) ?? '2099-01-01'
            ],
            [
                'saldo_stock' => $saldoStock,
                'cantidad' => $cantidad,
            ]
        );

        $this->importedCount++;
        return $inventario;
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
        return count($this->failures());
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
