# Instrucciones para Implementar Paginación y Búsqueda

Este documento explica cómo aplicar paginación y búsqueda a todos los controladores del sistema.

## Backend (Laravel)

### 1. Usar el Trait HasPagination

El trait `HasPagination` ya está creado en `app/Http/Traits/HasPagination.php` y proporciona métodos reutilizables.

### 2. Actualizar Controladores

Para cada controlador que necesite paginación y búsqueda:

#### Paso 1: Importar el trait
```php
use App\Http\Traits\HasPagination;
```

#### Paso 2: Usar el trait en la clase
```php
class TuController extends Controller
{
    use HasPagination;
    // ...
}
```

#### Paso 3: Actualizar el método index
```php
public function index(Request $request)
{
    $query = TuModelo::with(['relaciones']); // Cargar relaciones necesarias
    
    // Definir campos buscables
    $searchableFields = [
        'campo1',
        'campo2',
        'relacion.campo', // Para buscar en relaciones
    ];
    
    // Aplicar búsqueda
    $query = $this->applySearch($query, $request, $searchableFields);
    
    // Definir campos ordenables
    $sortableFields = ['id', 'nombre', 'created_at'];
    
    // Aplicar ordenamiento
    $query = $this->applySorting($query, $request, $sortableFields, 'id', 'desc');
    
    // Aplicar paginación
    return $this->paginateResponse($query, $request, 15, 100);
}
```

### 3. Campos de Búsqueda Recomendados por Modelo

- **Articulos**: código, nombre, descripción, categoría, marca, proveedor
- **Clientes**: nombre, teléfono, email, NIT, dirección
- **Proveedores**: nombre, teléfono, email, NIT, dirección
- **Ventas**: ID, número de comprobante, cliente, fecha
- **Compras**: ID, número de comprobante, proveedor, fecha
- **Cajas**: ID, sucursal, usuario, fecha
- **Usuarios**: nombre, email
- **Categorías/Marcas/Medidas**: nombre
- **Sucursales**: nombre, dirección

## Frontend (Angular)

### 1. Actualizar Servicios

Agregar el método `getPaginated` a cada servicio:

```typescript
getPaginated(params?: PaginationParams): Observable<ApiResponse<PaginatedResponse<TuModelo>>> {
    let httpParams = new HttpParams();
    
    if (params?.page) {
        httpParams = httpParams.set('page', params.page.toString());
    }
    if (params?.per_page) {
        httpParams = httpParams.set('per_page', params.per_page.toString());
    }
    if (params?.search) {
        httpParams = httpParams.set('search', params.search);
    }
    if (params?.sort_by) {
        httpParams = httpParams.set('sort_by', params.sort_by);
    }
    if (params?.sort_order) {
        httpParams = httpParams.set('sort_order', params.sort_order);
    }
    
    return this.http.get<ApiResponse<PaginatedResponse<TuModelo>>>(this.apiUrl, { params: httpParams });
}
```

### 2. Actualizar Componentes

#### En el componente TypeScript:
```typescript
// Agregar imports
import { SearchBarComponent } from '../../../shared/components/search-bar/search-bar.component';
import { PaginationComponent } from '../../../shared/components/pagination/pagination.component';
import { PaginationParams } from '../../../interfaces';

// Agregar propiedades
currentPage: number = 1;
lastPage: number = 1;
total: number = 0;
perPage: number = 15;
searchTerm: string = '';

// Actualizar método de carga
loadItems(): void {
    this.isLoading = true;
    
    const params: PaginationParams = {
        page: this.currentPage,
        per_page: this.perPage,
        sort_by: 'id',
        sort_order: 'desc'
    };
    
    if (this.searchTerm) {
        params.search = this.searchTerm;
    }
    
    this.tuService.getPaginated(params)
        .pipe(finalize(() => this.isLoading = false))
        .subscribe({
            next: (response) => {
                if (response.data) {
                    this.items = response.data.data || [];
                    this.currentPage = response.data.current_page;
                    this.lastPage = response.data.last_page;
                    this.total = response.data.total;
                    this.perPage = response.data.per_page;
                }
            },
            error: (error) => console.error('Error loading items', error)
        });
}

// Métodos para búsqueda y paginación
onSearch(search: string): void {
    this.searchTerm = search;
    this.currentPage = 1;
    this.loadItems();
}

onSearchClear(): void {
    this.searchTerm = '';
    this.currentPage = 1;
    this.loadItems();
}

onPageChange(page: number): void {
    this.currentPage = page;
    this.loadItems();
}
```

#### En el componente HTML:
```html
<!-- Agregar imports en el decorador @Component -->
imports: [
    // ... otros imports
    SearchBarComponent,
    PaginationComponent
]

<!-- Agregar buscador -->
<div class="mb-4 max-w-md">
    <app-search-bar 
        placeholder="Buscar..."
        (search)="onSearch($event)"
        (clear)="onSearchClear()">
    </app-search-bar>
</div>

<!-- Lista existente -->
<app-tu-lista [items]="items" [isLoading]="isLoading">
</app-tu-lista>

<!-- Agregar paginación -->
<app-pagination 
    [currentPage]="currentPage"
    [lastPage]="lastPage"
    [total]="total"
    [perPage]="perPage"
    (pageChange)="onPageChange($event)">
</app-pagination>
```

## Controladores Pendientes

Los siguientes controladores necesitan ser actualizados siguiendo el patrón anterior:

- [ ] VentaController
- [ ] CompraController
- [ ] CotizacionController
- [ ] CreditoVentaController
- [ ] TraspasoController
- [ ] InventarioController
- [ ] UserController
- [ ] RolController
- [ ] SucursalController
- [ ] AlmacenController
- [ ] EmpresaController
- [ ] CategoriaController
- [ ] MarcaController
- [ ] MedidaController
- [ ] IndustriaController
- [ ] MonedaController
- [ ] PrecioController
- [ ] TipoVentaController
- [ ] TipoPagoController
- [ ] NotificationController
- [ ] TransaccionCajaController
- [ ] CuotaCreditoController
- [ ] CompraCuotaController
- [ ] ConfiguracionTrabajoController

## Notas Importantes

1. **Rendimiento**: La búsqueda usa `LIKE` con índices. Para mejor rendimiento, considera agregar índices a los campos más buscados.

2. **Límites**: El máximo de items por página está configurado en 100 para evitar sobrecarga.

3. **Compatibilidad**: Los métodos `getAll()` existentes siguen funcionando para mantener compatibilidad.

4. **Búsqueda en Relaciones**: Usa la sintaxis `relacion.campo` para buscar en relaciones.

5. **Ordenamiento por Defecto**: Se ordena por ID descendente por defecto (más recientes primero).

