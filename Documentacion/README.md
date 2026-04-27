# CLAUDE.md — Router Maestro

## Identidad del proyecto

Sistema: Capa inteligente sobre ERP legacy (TINI) para Comercial San Francisco.
Cliente: Ferretería con 4 sucursales en Ecuador (3 Riobamba, 1 Macas).
Agencia: Pherce (automatización).
TINI es COBOL + archivos .dat. Es de SOLO LECTURA. Nunca se modifica. Nunca se reemplaza.

---

## Reglas absolutas (el agente NUNCA puede violar estas)

1. **TINI es intocable.** Nunca generar código que escriba archivos .dat ni modifique TINI.
2. **Schema `tini_raw` es de solo lectura para la app.** Solo el ETL escribe ahí. Ni migraciones, ni seeders, ni models con `save()` en ese schema.
3. **Nunca duplicar entrada de datos.** Si un dato ya se ingresa en TINI, el sistema lo lee del ETL. Nunca pedir al usuario que lo ingrese de nuevo.
4. **Branch scope siempre activo.** Todo query que toque datos operativos DEBE filtrar por `branch_id`. Sin excepciones salvo el rol Dueño.
5. **Auditoría obligatoria.** Toda escritura en `pherce_intel` genera log de auditoría. Sin excepciones.
6. **No instalar paquetes no autorizados.** El stack está cerrado. Si necesitas algo que no está en architecture.md, DETENTE y pregunta.
7. **No improvisar estructura.** Cada módulo sigue el patrón: `Models/`, `Services/`, `Jobs/`, `Events/`, `Policies/`. Sin variaciones.
8. **Cifrar campos sensibles.** RUC, datos de proveedores, info de empleados → trait `Encryptable`. Nunca texto plano.
9. **No exponer lógica de negocio en controllers.** Controllers delegan a Services. Controllers solo validan request, llaman service, retornan response.
10. **Respetar los límites de cada schema.** `public` = sistema. `tini_raw` = réplica TINI. `pherce_intel` = inteligencia nueva. No mezclar.

---

## Índice de documentos del proyecto

| Documento              | Ruta relativa             | Contenido clave                                   |
| ---------------------- | ------------------------- | ------------------------------------------------- |
| project-context.md     | `docs/project-context.md` | Cliente, problema, restricciones inamovibles       |
| architecture.md        | `docs/architecture.md`    | Stack, capas, patrones, estructura de módulos      |
| database-schema.md     | `docs/database-schema.md` | Tres schemas, tablas, tipos, relaciones, reglas    |
| business-rules.md      | `docs/business-rules.md`  | Reglas de dominio, scope sucursal, flujo factura   |
| security-rules.md      | `docs/security-rules.md`  | Cifrado, auditoría, lo que nunca se genera         |
| coding-standards.md    | `docs/coding-standards.md`| Convenciones PHP/TS, patrones, carpetas            |
| user-flows.md          | `docs/user-flows.md`      | Flujos críticos como listas numeradas              |

---

## Router de contexto: qué cargar según la tarea

**Regla de carga:** Máximo en contexto simultáneo = este CLAUDE.md + 2 docs del proyecto + MCP (Context7 / Laravel Boost).

**`project-context.md` solo se carga en la primera tarea de una sesión nueva o cuando se necesite recordar quién es el cliente.**

| Tipo de tarea                        | Documentos a cargar (además de CLAUDE.md)          |
| ------------------------------------ | -------------------------------------------------- |
| Scaffold / estructura nueva          | architecture.md + database-schema.md + MCP         |
| Lógica de negocio                    | business-rules.md + user-flows.md + MCP            |
| Seguridad / permisos / auditoría     | security-rules.md + business-rules.md + MCP        |
| UI / páginas Inertia + React         | coding-standards.md + user-flows.md + MCP          |
| ETL / TINI bridge                    | database-schema.md + architecture.md + MCP         |
| Bug fix / debug                      | (solo MCP — Context7 / Laravel Boost)              |
| Migración / schema change            | database-schema.md + architecture.md + MCP         |
| Nuevo módulo completo                | architecture.md + business-rules.md + MCP          |

---

## Estructura de dominio

```
app/Domain/
├── Branches/          # Sucursales, middleware BranchScope
├── Auth/              # Login, RBAC (Spatie Permission)
├── EtlBridge/         # Lectura .dat → tini_raw (el puente con TINI)
├── Inventory/         # Stock, mínimos, caducidad, lotes
├── Purchasing/        # Compras, recepción de facturas, proveedores
├── Sales/             # Ventas, crédito, historial
├── Accounting/        # Consulta contable sobre datos TINI
├── Warehouse/         # Bodega, confirmaciones, transferencias
├── Notifications/     # Alertas (stock bajo, caducidad, pendientes)
└── Audit/             # Logs inmutables, cumplimiento LOPDP
```

Cada módulo internamente:
```
Domain/{Module}/
├── Models/
├── Services/
├── Jobs/
├── Events/
├── Policies/
└── DTOs/              # Spatie Data
```

Traits compartidos en `app/Shared/Traits/`:
- `BranchScoped` — aplica filtro automático por branch_id
- `Auditable` — registra toda escritura con spatie/activitylog
- `Encryptable` — cifra campos sensibles en reposo

---

## Laravel Boost — MCP obligatorio

**Laravel Boost está instalado y activo** (`laravel/boost ^2.4`). Es el servidor MCP principal para todo desarrollo en este proyecto.

**Herramientas MCP disponibles vía `laravel-boost`:**
- `search-docs` — Busca documentación de Laravel 13, Inertia 3, Fortify, Horizon, Pulse, Reverb, Wayfinder, Tailwind, PHPUnit. **OBLIGATORIO usarlo en vez del conocimiento de entrenamiento.**
- `database-schema` — Inspecciona tablas, columnas y relaciones en PostgreSQL.
- `database-query` — Ejecuta queries de lectura contra la DB.
- `application-info` — Versiones, paquetes instalados, modelos Eloquent.
- `last-error` / `read-log-entries` — Lee logs y errores de la app.
- `artisan` — Lista y ejecuta comandos Artisan.

**Skills activadas automáticamente según contexto:**
`fortify-development`, `laravel-best-practices`, `configuring-horizon`, `pulse-development`, `wayfinder-development`, `inertia-react-development`, `tailwindcss-development`, `echo-react-development`, `echo-development`

**Regla absoluta:** NUNCA usar conocimiento de entrenamiento para APIs de Laravel, Inertia, Spatie, Reverb, Echo, Wayfinder, Tailwind, PHPUnit. Siempre llamar `search-docs` del MCP `laravel-boost` para verificar sintaxis exacta de la versión instalada.

**Para actualizar guidelines/skills después de `composer update`:** se ejecuta automáticamente via `post-update-cmd`. También se puede correr manualmente: `php artisan boost:update`.

**El CLAUDE.md generado por Boost** está en `app/CLAUDE.md` y se carga automáticamente. No modificarlo manualmente — se regenera con `boost:install`.

---

## Convenciones rápidas (detalle completo en coding-standards.md)

- PHP: PSR-12, strict types, type hints en todo
- TypeScript: strict mode, no `any`, interfaces explícitas
- Nombres: Models en singular inglés (`Product`, `Branch`), tablas en plural snake_case (`products`, `branches`)
- Rutas: `/{module}/{action}` — resource routes cuando aplique
- Tests: Feature tests para Services, no unit tests de Models

---

## Secciones marcadas como pendiente

Algunos documentos contienen secciones marcadas con `<!-- PENDIENTE: ... -->`.
Esto significa que la información aún no está definida. Cuando encuentres un bloque PENDIENTE:

1. **NO inventes datos** para llenar el vacío.
2. **NO asumas** una implementación.
3. **DETENTE y pregunta** al desarrollador antes de continuar con esa parte.
4. Puedes continuar trabajando en otras partes que sí estén definidas.

---

## Verificación pre-commit (checklist mental del agente)

Antes de considerar cualquier tarea terminada:

- [ ] ¿El código respeta branch scope? ¿Todo query filtra por branch_id?
- [ ] ¿Se genera log de auditoría en toda escritura a pherce_intel?
- [ ] ¿Los campos sensibles usan el trait Encryptable?
- [ ] ¿La lógica de negocio está en Service, no en Controller?
- [ ] ¿Se usó MCP para verificar sintaxis de Laravel/Spatie en vez de memoria?
- [ ] ¿Se respetó la estructura de carpetas del módulo?
- [ ] ¿No se escribió nada en tini_raw fuera del ETL?
