# database-schema.md — Schemas, Tablas, Relaciones y Reglas

## Principio rector

PostgreSQL 16 con tres schemas que representan tres dominios de datos distintos.
Cada schema tiene reglas de escritura estrictas. Violarlas es un error crítico.

| Schema          | Propósito                                 | Quién escribe                | Quién lee                    |
| --------------- | ----------------------------------------- | ---------------------------- | ---------------------------- |
| `public`        | Sistema: usuarios, roles, config, sesiones | App (Laravel)                | App (Laravel)                |
| `tini_raw`      | Réplica de TINI. Espejo de los .dat       | Solo ETL (EtlBridge Jobs)    | App (solo lectura)           |
| `pherce_intel`  | Inteligencia nueva que TINI no tiene      | App (Laravel)                | App (Laravel)                |

**Regla absoluta:** La aplicación Laravel NUNCA escribe en `tini_raw`. Ni migrations con INSERT, ni seeders, ni Models con save(). Solo el ETL.

---

## Schema: `public`

Entidades del sistema base. Auth, RBAC, configuración global, sucursales.

### `branches` — Sucursales
```
id              BIGINT PK AUTO
name            VARCHAR(100) NOT NULL          -- "Riobamba Centro", "Macas"
code            VARCHAR(10) UNIQUE NOT NULL     -- "RIO1", "RIO2", "RIO3", "MAC1"
address         TEXT
city            VARCHAR(50) NOT NULL
is_headquarters BOOLEAN DEFAULT false           -- true solo para sede principal
is_active       BOOLEAN DEFAULT true
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `users` — Usuarios del sistema
```
id              BIGINT PK AUTO
name            VARCHAR(100) NOT NULL
email           VARCHAR(255) UNIQUE NOT NULL
password        VARCHAR(255) NOT NULL           -- hash bcrypt via Fortify (BCRYPT_ROUNDS=12)
active_branch_id BIGINT FK → branches.id NULL   -- sucursal seleccionada en sesión
is_active       BOOLEAN DEFAULT true
remember_token  VARCHAR(100) NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `user_branch` — Relación usuario ↔ sucursal (muchos a muchos)
```
id              BIGINT PK AUTO
user_id         BIGINT FK → users.id NOT NULL
branch_id       BIGINT FK → branches.id NOT NULL
assigned_at     TIMESTAMP DEFAULT now()
UNIQUE(user_id, branch_id)
```
Los roles se asignan via Spatie Permission (tablas `roles`, `permissions`, `model_has_roles`, `model_has_permissions` las genera el paquete automáticamente). No crear tablas de roles manuales.

### `app_settings` — Configuración global del sistema
```
id              BIGINT PK AUTO
key             VARCHAR(100) UNIQUE NOT NULL    -- "etl_frequency", "alert_check_interval"
value           TEXT NOT NULL
description     TEXT NULL
updated_by      BIGINT FK → users.id NULL
updated_at      TIMESTAMP
```

### Tablas generadas por paquetes (NO crear manualmente)
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` → **Spatie Permission**
- `activity_log` → **Spatie Activity Log**
- `sessions` → **Laravel** (driver database si se usa, o Redis)
- `jobs`, `failed_jobs`, `job_batches` → **Laravel Queue**
- `cache`, `cache_locks` → **Laravel** (si se usa driver database; preferir Redis)

---

## Schema: `tini_raw`

Réplica fiel de los archivos `.dat` de TINI. Las tablas aquí son un espejo del contenido de cada archivo.

**Reglas de este schema:**
1. Solo el ETL (Jobs en `Domain/EtlBridge/`) escribe aquí
2. La app lee con Models de solo lectura (sin trait `Auditable`, sin `save()`)
3. Cada tabla tiene `etl_synced_at` (timestamp de última sincronización)
4. Los datos se almacenan tal como vienen de TINI, sin transformación de negocio
5. Los Models de tini_raw definen `$connection` o usan `$table = 'tini_raw.tabla'`

<!-- PENDIENTE: Definición exacta de tablas de tini_raw. Depende de la exploración de los archivos .dat del servidor TINI. Cuando se defina, documentar aquí cada tabla con sus campos exactos mapeados del .dat. -->

**Tablas esperadas (estructura por confirmar):**
```
tini_raw.products           -- catálogo de productos
tini_raw.suppliers          -- proveedores
tini_raw.customers          -- clientes
tini_raw.purchase_invoices  -- facturas de compra
tini_raw.sale_invoices      -- facturas de venta
tini_raw.inventory_movements -- movimientos de inventario
tini_raw.accounts           -- plan de cuentas contable
tini_raw.transactions       -- asientos contables
```

**Columnas comunes que el ETL agrega a todas las tablas de tini_raw:**
```
etl_synced_at   TIMESTAMP NOT NULL             -- cuándo se leyó este registro del .dat
etl_batch_id    VARCHAR(50) NOT NULL           -- identificador de la corrida ETL
etl_source_file VARCHAR(100) NOT NULL          -- nombre del archivo .dat de origen
tini_raw_id     BIGINT PK AUTO                 -- PK interna (no es el ID de TINI)
tini_record_id  VARCHAR(50) NOT NULL           -- ID original del registro en TINI
```

### Models de tini_raw: patrón obligatorio

```php
// app/Domain/EtlBridge/Models/TiniProduct.php
class TiniProduct extends Model
{
    protected $table = 'tini_raw.products';

    // NUNCA permitir escritura desde la app
    public function save(array $options = []): bool
    {
        if (!app()->runningInConsole() || !app('etl.running')) {
            throw new \RuntimeException('tini_raw models are read-only for the application.');
        }
        return parent::save($options);
    }

    // Sin trait Auditable (el ETL tiene su propio log)
    // Sin trait BranchScoped (depende de cómo TINI separe sucursales)
}
```

---

## Schema: `pherce_intel`

Entidades que TINI no tiene. Aquí vive toda la inteligencia nueva del sistema.

### `stock_thresholds` — Umbrales de stock mínimo
```
id              BIGINT PK AUTO
branch_id       BIGINT FK → branches.id NOT NULL
tini_product_id VARCHAR(50) NOT NULL           -- referencia al ID de producto en TINI
minimum_qty     DECIMAL(10,2) NOT NULL
reorder_qty     DECIMAL(10,2) NULL             -- cantidad sugerida de reorden
is_active       BOOLEAN DEFAULT true
set_by          BIGINT FK → users.id NOT NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
UNIQUE(branch_id, tini_product_id)
```

### `product_expiry_tracking` — Control de caducidad y lotes
```
id              BIGINT PK AUTO
branch_id       BIGINT FK → branches.id NOT NULL
tini_product_id VARCHAR(50) NOT NULL
batch_number    VARCHAR(50) NULL               -- número de lote si aplica
expiry_date     DATE NOT NULL
quantity        DECIMAL(10,2) NOT NULL
status          VARCHAR(20) DEFAULT 'active'   -- active | expiring_soon | expired
noted_by        BIGINT FK → users.id NOT NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `reception_confirmations` — Confirmaciones de recepción en bodega
```
id              BIGINT PK AUTO
branch_id       BIGINT FK → branches.id NOT NULL
tini_invoice_id VARCHAR(50) NOT NULL           -- referencia a factura en TINI
confirmed_by    BIGINT FK → users.id NOT NULL
status          VARCHAR(20) DEFAULT 'pending'  -- pending | confirmed | discrepancy
notes           TEXT NULL
confirmed_at    TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `reception_confirmation_items` — Detalle línea por línea de recepción
```
id                      BIGINT PK AUTO
confirmation_id         BIGINT FK → reception_confirmations.id NOT NULL
tini_product_id         VARCHAR(50) NOT NULL
expected_qty            DECIMAL(10,2) NOT NULL  -- lo que dice la factura
received_qty            DECIMAL(10,2) NOT NULL  -- lo que bodega contó
has_discrepancy         BOOLEAN DEFAULT false
discrepancy_notes       TEXT NULL
```

### `alerts` — Alertas generadas por el sistema
```
id              BIGINT PK AUTO
branch_id       BIGINT FK → branches.id NOT NULL
type            VARCHAR(50) NOT NULL           -- stock_low | expiry_near | accounting_discrepancy | ...
severity        VARCHAR(20) DEFAULT 'warning'  -- info | warning | critical
title           VARCHAR(200) NOT NULL
body            TEXT NULL
reference_type  VARCHAR(50) NULL               -- modelo relacionado (polymorphic)
reference_id    BIGINT NULL                    -- ID del registro relacionado
is_read         BOOLEAN DEFAULT false
is_resolved     BOOLEAN DEFAULT false
resolved_by     BIGINT FK → users.id NULL
resolved_at     TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `alert_subscriptions` — Quién recibe qué tipo de alerta
```
id              BIGINT PK AUTO
user_id         BIGINT FK → users.id NOT NULL
alert_type      VARCHAR(50) NOT NULL           -- coincide con alerts.type
branch_id       BIGINT FK → branches.id NULL   -- NULL = todas las sucursales
channel         VARCHAR(20) DEFAULT 'web'      -- web | email
is_active       BOOLEAN DEFAULT true
UNIQUE(user_id, alert_type, branch_id)
```

### `branch_transfers` — Transferencias entre sucursales
```
id              BIGINT PK AUTO
from_branch_id  BIGINT FK → branches.id NOT NULL
to_branch_id    BIGINT FK → branches.id NOT NULL
status          VARCHAR(20) DEFAULT 'initiated' -- initiated | in_transit | received | cancelled
initiated_by    BIGINT FK → users.id NOT NULL
received_by     BIGINT FK → users.id NULL
notes           TEXT NULL
initiated_at    TIMESTAMP DEFAULT now()
received_at     TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `branch_transfer_items` — Detalle de productos transferidos
```
id              BIGINT PK AUTO
transfer_id     BIGINT FK → branch_transfers.id NOT NULL
tini_product_id VARCHAR(50) NOT NULL
quantity        DECIMAL(10,2) NOT NULL
received_qty    DECIMAL(10,2) NULL
```

### `etl_execution_log` — Log de cada corrida del ETL
```
id              BIGINT PK AUTO
started_at      TIMESTAMP NOT NULL
finished_at     TIMESTAMP NULL
status          VARCHAR(20) DEFAULT 'running'  -- running | completed | failed
source_files    JSONB NULL                     -- lista de archivos procesados
records_read    INTEGER DEFAULT 0
records_written INTEGER DEFAULT 0
records_failed  INTEGER DEFAULT 0
error_message   TEXT NULL
```

---

## Reglas de escritura por schema (resumen para el agente)

| Acción                                  | `public` | `tini_raw` | `pherce_intel` |
| --------------------------------------- | -------- | ---------- | -------------- |
| Migration CREATE/ALTER                  | ✅        | ✅ (solo ETL tables) | ✅          |
| Model::create() / save() desde app     | ✅        | ❌ PROHIBIDO | ✅            |
| Seeder INSERT                           | ✅        | ❌ PROHIBIDO | ✅            |
| ETL Job INSERT/UPDATE                   | ❌        | ✅ ÚNICO ESCRITOR | ❌          |
| SELECT / lectura                        | ✅        | ✅          | ✅             |

---

## Convenciones de naming

- Tablas: plural snake_case inglés (`stock_thresholds`, `branches`)
- Columnas: snake_case (`tini_product_id`, `created_at`)
- Foreign keys: `{tabla_singular}_id` (`branch_id`, `user_id`)
- Referencias a TINI: siempre `tini_{entidad}_id` con tipo VARCHAR(50) — nunca FK real a tini_raw
- Timestamps: `created_at` + `updated_at` en toda tabla (Eloquent default)
- Soft deletes: NO usamos soft deletes. Si algo se desactiva, usa campo `is_active`.

## Referencias cruzadas entre schemas

Los datos de `pherce_intel` referencian productos/facturas de TINI mediante `tini_product_id` o `tini_invoice_id` como VARCHAR. **No son foreign keys reales** a `tini_raw` porque:
1. El ETL puede recargar/recrear registros en `tini_raw`
2. Los IDs de TINI son strings, no integers auto-incrementales
3. La integridad se valida en la capa de Service, no en la DB

---

<!-- PENDIENTE: Tablas adicionales de pherce_intel que surgirán con módulos de Ventas (proformas), Contabilidad (conciliaciones automáticas), y Dashboard gerencial. Se agregarán cuando se construyan esas fases. -->
