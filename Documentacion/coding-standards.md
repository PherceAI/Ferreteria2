# coding-standards.md — Convenciones PHP/TypeScript, Patrones y Estructura

## PHP (Backend — Laravel 13)

### Reglas generales
- **PSR-12** como base de estilo
- `declare(strict_types=1);` en todo archivo PHP
- Type hints en parámetros, retornos y propiedades — sin excepciones
- No usar `mixed` ni suprimir tipos. Si no sabes el tipo, el diseño está mal.
- Return types explícitos en todos los métodos públicos

### Naming
- **Models:** singular, PascalCase, inglés (`Product`, `Branch`, `StockThreshold`)
- **Tables:** plural, snake_case, inglés (`products`, `branches`, `stock_thresholds`)
- **Controllers:** PascalCase, sufijo Controller (`InventoryController`, `AlertController`)
- **Services:** PascalCase, sufijo Service (`StockCalculationService`, `EtlSyncService`)
- **Jobs:** PascalCase, verbo + sustantivo (`SyncTiniProducts`, `CheckStockThresholds`)
- **Events:** PascalCase, pasado (`ReceptionConfirmed`, `AlertTriggered`, `EtlCompleted`)
- **DTOs:** PascalCase, sufijo Data (`ProductData`, `AlertData`) — extender `Spatie\LaravelData\Data`
- **Policies:** PascalCase, sufijo Policy (`ReceptionConfirmationPolicy`)
- **FormRequests:** PascalCase, verbo + recurso + Request (`StoreReceptionRequest`, `UpdateThresholdRequest`)

### Estructura de carpetas (Domain-driven)
```
app/
├── Domain/
│   ├── {Module}/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   ├── Events/
│   │   ├── Policies/
│   │   └── DTOs/
│   └── ...
├── Http/
│   ├── Controllers/          # Organizados por módulo: Controllers/Inventory/, Controllers/Purchasing/
│   ├── Middleware/
│   └── Requests/             # FormRequests organizados por módulo
├── Shared/
│   └── Traits/
│       ├── BranchScoped.php
│       ├── Auditable.php
│       └── Encryptable.php
├── Providers/
└── Exceptions/
```

### Patrón Controller → Service → Model
```php
// Controller: solo valida y delega
public function store(StoreReceptionRequest $request): RedirectResponse
{
    $data = ReceptionData::from($request->validated());
    $this->receptionService->confirm($data);
    return redirect()->back()->with('success', 'Recepción confirmada.');
}

// Service: toda la lógica
public function confirm(ReceptionData $data): ReceptionConfirmation
{
    // Lógica de negocio aquí
    // Disparar eventos aquí
    // Retornar resultado
}

// Model: relaciones, scopes, traits. NUNCA lógica de negocio.
```

### Reglas de Eloquent
- Definir `$fillable` explícitamente (nunca `$guarded = []`)
- Relaciones tipadas con return type
- Scopes como métodos `scope{Name}` o traits
- Casts explícitos para dates, booleans, decimals, JSON

---

## TypeScript (Frontend — React 19 + Inertia 3)

### Reglas generales
- **Strict mode** activado en `tsconfig.json`
- No usar `any`. Nunca. Si llega un tipo desconocido, definir una interface.
- Interfaces para todo: props, responses, forms, estados

### Naming
- **Componentes:** PascalCase, archivos `.tsx` (`InventoryDashboard.tsx`, `AlertCard.tsx`)
- **Hooks custom:** camelCase, prefijo `use` (`useAlerts.ts`, `useBranchScope.ts`)
- **Types/Interfaces:** PascalCase, sufijo descriptivo (`ProductProps`, `AlertFormData`)
- **Páginas Inertia:** PascalCase, en carpeta por módulo (`Pages/Inventory/Index.tsx`, `Pages/Purchasing/Receptions.tsx`)

### Estructura de carpetas frontend
```
resources/js/
├── Pages/                    # Páginas Inertia (una por ruta)
│   ├── Inventory/
│   ├── Purchasing/
│   ├── Warehouse/
│   ├── Dashboard/
│   └── Auth/
├── Components/               # Componentes reutilizables
│   ├── ui/                   # shadcn/ui components
│   ├── Layout/               # Layout principal, sidebar, navbar
│   └── Shared/               # Componentes compartidos entre módulos
├── Hooks/                    # Custom hooks
├── Types/                    # Interfaces y tipos globales
│   └── generated.d.ts       # Tipos generados desde Laravel (Spatie TypeScript Transformer si se usa)
└── Lib/                      # Utilidades
```

### Inertia: convenciones
- Props de página tipadas con interface explícita
- Usar `useForm()` de Inertia para formularios (maneja CSRF automáticamente)
- Usar `router.visit()` o `Link` para navegación — nunca `window.location`
- Datos compartidos (user, branch, permissions) vía `usePage().props`

### shadcn/ui
- Usar componentes de shadcn como base. No reinventar inputs, modals, tables.
- Personalizar via Tailwind classes, no CSS custom
- Respetar el sistema de colores de shadcn (CSS variables en `globals.css`)

---

## Tests

- **Feature tests** para Services: testar flujos completos (crear recepción → confirmar → verificar stock)
- **No unit tests de Models** — no aportan valor en este contexto
- **Tests de Policies** — verificar que cada rol tiene los permisos correctos
- Tests se organizan en carpeta por módulo: `tests/Feature/Inventory/`, `tests/Feature/Purchasing/`
- Usar factories para generar datos de prueba
- Base de datos de test usa los mismos 3 schemas

---

## Git

- Commits en inglés, formato: `type(scope): description`
- Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`
- Ejemplo: `feat(inventory): add stock threshold validation`
- Una feature por branch, merge via PR
