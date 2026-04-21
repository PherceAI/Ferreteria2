# architecture.md — Stack, Capas, Patrones y Módulos

## Stack tecnológico (cerrado — no agregar sin autorización)

### Backend
- **Laravel 13** · PHP 8.3 · Eloquent ORM
- **Laravel Fortify** — autenticación nativa (email + password, 2FA TOTP, recovery codes, password reset, verificación de email). No WorkOS, no Socialite, no Breeze.
- **Spatie Permission** — RBAC con roles y permisos granulares
- **Spatie Activity Log** — auditoría inmutable (cumplimiento LOPDP Ecuador)
- **Spatie Data** — DTOs tipados para transferencia entre capas
- **Spatie Backup** — backups automáticos programados

### Frontend
- **React 19** · **TypeScript** (strict mode) · **Inertia 3**
- **shadcn/ui** · **Tailwind CSS**

### Tiempo real
- **Laravel Reverb** — WebSocket server nativo (mismo VPS, no servicio externo)
- **Laravel Echo** — cliente JS para suscripción a canales

### Background processing
- **Laravel Queue** con driver **Redis**
- **Laravel Scheduler** — cron para alertas y ETL
- **Supervisor** — mantiene vivos workers y Reverb

### Monitoreo
- **Laravel Pulse** — salud del sistema en producción (gratuito). Ingest vía Redis (`PULSE_INGEST_DRIVER=redis`).
- **Laravel Horizon** — panel de colas en producción (gratuito).
- **Laravel Telescope** — debugging solo en desarrollo (nunca en producción).

> Los tres dashboards están detrás de Gates reales (`viewPulse`, `viewHorizon`, `viewTelescope`) definidos en `AppServiceProvider`/`HorizonServiceProvider`/`TelescopeServiceProvider`. La lista de emails y roles permitidos vive en `config/internal.php` (`observability_emails`, `observability_roles`), alimentada por `OBSERVABILITY_EMAILS` en `.env`. Por defecto solo el rol `Dueño` ve observabilidad.

### Base de datos
- **PostgreSQL 16** — una sola base `ferreteria` con tres schemas: `public`, `pherce_intel`, `tini_raw`.
- **Dos roles Postgres** sobre esa misma base:
  - `ferreteria_app` (usuario de la app Laravel) — RW en `public` y `pherce_intel`, **solo SELECT** en `tini_raw`.
  - `ferreteria_etl` (usuario del ETL) — **único con escritura** sobre `tini_raw`; sin acceso a `public`/`pherce_intel`.
- La separación vive en `docker/postgres/init/01-create-schemas.sql` (arranque limpio) y en `02-sync-existing-volume.sql` (aplicación idempotente sobre un volumen preexistente).
- **Redis 7** — un servicio, cuatro bases lógicas separadas: `db=1` cache, `db=2` colas, `db=3` sesiones, `db=4` Pulse. Sesiones cifradas (`SESSION_ENCRYPT=true`).

### Infraestructura
- VPS Ubuntu 22.04 gestionado por Pherce
- Nginx + Let's Encrypt (SSL/TLS)
- PHP 8.3 FPM

---

## Arquitectura de capas

```
┌─────────────────────────────────────────────────────┐
│  FRONTEND (React 19 + Inertia 3 + shadcn/ui)       │
│  Cada página es un componente React.                │
│  Inertia elimina la necesidad de API REST.          │
│  Echo escucha eventos WebSocket de Reverb.          │
└─────────────────┬───────────────────────────────────┘
                  │ Inertia requests (no API pura)
┌─────────────────▼───────────────────────────────────┐
│  CONTROLLERS (validación + delegación)              │
│  FormRequest para validación.                       │
│  Llaman a Services. Nunca contienen lógica.         │
│  Retornan Inertia::render() o redirects.            │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│  SERVICES (lógica de negocio)                       │
│  Toda la lógica vive aquí. Orquestan Models,        │
│  disparan Events, encolan Jobs.                     │
│  Reciben y retornan DTOs (Spatie Data).             │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│  MODELS + ELOQUENT                                  │
│  Relaciones, scopes, traits (BranchScoped,          │
│  Auditable, Encryptable). Sin lógica de negocio.    │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│  POSTGRESQL (3 schemas)                             │
│  public: usuarios, roles, sucursales, config        │
│  tini_raw: réplica ETL (solo lectura para app)      │
│  pherce_intel: entidades nuevas del sistema         │
└─────────────────────────────────────────────────────┘
```

---

## Modelo de datos: dos verdades

**Verdad transaccional** → `tini_raw`. Lo que TINI dice que pasó. Solo el ETL escribe aquí.
**Verdad operacional** → `pherce_intel`. Lo que TINI no sabe: umbrales, alertas, confirmaciones, lotes, caducidad.

**Regla de cruce:** cuando el sistema necesita información completa (ej: stock real disponible), cruza datos de `tini_raw` con datos de `pherce_intel`. El resultado es una vista calculada, nunca un dato duplicado.

---

## Multi-sucursal: Branch Scope

No es multi-tenancy. No son bases de datos separadas. Es un **scope inyectado por middleware**.

1. Usuario inicia sesión → selecciona sucursal activa
2. Middleware `BranchScope` inyecta `branch_id` en la request
3. Todo model con trait `BranchScoped` aplica filtro automático `where branch_id = ?`
4. El rol **Dueño** puede ver todas las sucursales (scope desactivado o scope global)

La tabla `user_branch` en schema `public` define qué usuarios acceden a qué sucursales.

---

## Estructura de módulos de dominio

```
app/Domain/
├── Branches/          # Gestión de sucursales, middleware BranchScope
├── Auth/              # Login, selección de sucursal, RBAC (Spatie Permission)
├── EtlBridge/         # Lectura .dat → tini_raw. Jobs programados. Parser de archivos.
├── Inventory/         # Stock calculado, mínimos, caducidad, lotes, estados
├── Purchasing/        # Recepción facturas (email + física), órdenes, proveedores
├── Sales/             # Consulta de ventas TINI, proformas, crédito, historial
├── Accounting/        # Conciliación automática, detección de descuadres
├── Warehouse/         # Confirmaciones de recepción, transferencias entre sucursales
├── Notifications/     # Motor de alertas: stock bajo, caducidad, descuadres
└── Audit/             # Logs inmutables, reportes de auditoría, cumplimiento LOPDP
```

**Estructura interna de cada módulo:**
```
Domain/{Module}/
├── Models/            # Eloquent models con traits
├── Services/          # Lógica de negocio (clases service)
├── Jobs/              # Queue jobs (procesamiento async)
├── Events/            # Domain events (Laravel events)
├── Policies/          # Authorization policies
└── DTOs/              # Spatie Data objects
```

**Traits compartidos** en `app/Shared/Traits/`:
- `BranchScoped` — query scope automático por branch_id
- `Auditable` — log de toda escritura via spatie/activitylog
- `Encryptable` — cifrado en reposo para campos sensibles

---

## ETL Bridge: el puente con TINI

El módulo `EtlBridge` es el único que interactúa con los archivos `.dat` de TINI.

**Flujo:** Archivos .dat (servidor TINI) → Parser (EtlBridge Job) → Inserta/actualiza en schema `tini_raw`

**Responsabilidades del ETL:**
- Leer y parsear los archivos .dat según su formato
- Detectar cambios desde la última lectura (delta o completa, según el archivo)
- Escribir únicamente en `tini_raw` — nunca en otro schema
- Registrar log de cada ejecución (timestamp, registros procesados, errores)

<!-- PENDIENTE: Frecuencia del ETL (nocturno vs. intraday). Decisión crítica que afecta la arquitectura del bridge y la frescura de datos en todo el sistema. -->

<!-- PENDIENTE: Formato exacto de los archivos .dat (ancho fijo COBOL vs. delimitado, encoding, campos). Se definirá al explorar el servidor TINI. -->

<!-- PENDIENTE: Mecanismo de acceso a los .dat (red local, montaje de carpeta, copia programada). Depende de la infraestructura de red entre VPS y servidor TINI. -->

---

## Tiempo real: Reverb + Echo

**Reverb** corre como proceso en el mismo VPS (Supervisor lo mantiene vivo).
**Echo** en el frontend se suscribe a canales por sucursal.

Casos de uso de WebSocket:
- Notificación instantánea de alertas (stock bajo, caducidad)
- Actualización de dashboard cuando el ETL completa una ejecución
- Confirmaciones de recepción en bodega visibles en tiempo real para compras

**Canal pattern:** `branch.{branch_id}` — cada sucursal tiene su canal privado.

---

## Lo que NO es este sistema

- **No es un ERP.** No reemplaza TINI para transacciones.
- **No tiene API pública.** Todo va por Inertia. No hay endpoints REST/GraphQL expuestos.
- **No es multi-tenant.** Es un solo cliente con múltiples sucursales.
- **No usa servicios externos de auth.** Fortify cubre todo para 15 usuarios internos: email + password, 2FA TOTP, recovery codes, reset de contraseña y verificación de email. No hay SSO ni OAuth.
- **No usa microservicios.** Es un monolito modular en un solo VPS.

---

## Bootstrap de Postgres (roles y grants)

La topología `ferreteria_app` / `ferreteria_etl` se crea con dos archivos bajo `app/docker/postgres/init/`:

- **`01-create-schemas.sql`** — lo ejecuta Postgres automáticamente en el **primer arranque** (volumen vacío). Crea schemas, roles, grants y `ALTER DEFAULT PRIVILEGES`. Si el volumen ya existía, este archivo **no se vuelve a correr**.
- **`02-sync-existing-volume.sql`** — idempotente. Se corre a mano cuando el volumen de Postgres ya existía antes de la refactorización (o cuando sospechas que los grants se salieron de sincronía):
  ```bash
  psql -U postgres -d ferreteria -f docker/postgres/init/02-sync-existing-volume.sql
  ```
  Usa `CREATE SCHEMA IF NOT EXISTS`, `DO $$ … IF NOT EXISTS`, y repite todos los `GRANT`/`ALTER DEFAULT PRIVILEGES` del script base. Es seguro correrlo varias veces.

**Regla:** después de correr migraciones nuevas con un usuario distinto a `ferreteria_app`, re-ejecutar `02-sync-existing-volume.sql` para que los grants cubran las tablas recién creadas (los `ALTER DEFAULT PRIVILEGES` cubren el caso de tablas futuras cuando las crea el mismo dueño, pero no re-otorgan permisos sobre tablas ya existentes con otro owner).

---

## Testing: niveles y ejecución

El proyecto tiene dos suites de test:

1. **Suite rápida (default)** — `phpunit.xml`. Corre sobre SQLite in-memory, cache/queue/session en memoria, broadcasting nulo. Se ejecuta con `php artisan test` o `composer test`. Es la que corre en CI (`lint.yml`) y la que hay que mantener siempre verde.

2. **Suite de integración (opt-in)** — `phpunit.integration.xml` + `tests/Integration/`. Corre contra Postgres y Redis reales leyendo `.env.testing`. Valida invariants que la suite SQLite no puede: que `BranchScoped` filtra contra Postgres real, que Cache/Session/Queue sobre Redis están bien cableados, y que los grants de `ferreteria_app` efectivamente bloquean escrituras accidentales sobre `tini_raw`. Se ejecuta con:
   ```bash
   composer test:integration
   ```
   No corre en CI por defecto — requiere levantar Postgres + Redis locales antes (`docker compose up -d postgres redis`). Si en algún momento se decide meterlo a CI, habría que añadir `services:` en `.github/workflows/lint.yml` con contenedores de Postgres y Redis; esa es una decisión operativa a tomar cuando los módulos de negocio maduren.
