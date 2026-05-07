# Laravel Boost en este proyecto

## Proposito

Laravel Boost es la capa oficial de Laravel para apoyar el desarrollo asistido por IA. En este proyecto sirve para que Codex trabaje con contexto real de la aplicacion: version de Laravel, rutas, configuracion, base de datos, logs, componentes instalados, convenciones del framework y documentacion especifica de los paquetes que usamos.

La idea practica es simple: antes de tocar codigo Laravel/Inertia, la IA debe consultar Boost o sus equivalentes locales para entender el estado real de la aplicacion. Esto reduce cambios inventados, evita patrones fuera de Laravel y ayuda a mantener el sistema limpio, seguro y escalable.

Referencia oficial:

- https://laravel.com/ai/boost
- https://laravel.com/docs/13.x/boost

## Estado en Ferreteria

Boost ya esta instalado en el proyecto.

- Paquete: `laravel/boost`
- Version instalada: `v2.4.3`
- Dependencia MCP: `laravel/mcp`
- Configuracion MCP del repo: `.mcp.json` ejecutando `php artisan boost:mcp --env=local`
- Configuracion de Boost: `boost.json`
- Guidelines generadas para Codex: `AGENTS.md`
- Skills locales para agentes: `.codex/` y `.claude/skills/`

Importante: Boost solo se activa en entorno `local` o cuando `APP_DEBUG=true`. Como la demo publica por Cloudflare Tunnel usa `APP_ENV=production` y `APP_DEBUG=false`, los comandos de Boost deben ejecutarse explicitamente con `--env=local`.

Ejemplo:

```bash
php artisan list boost --env=local
```

Esto es intencional y sano: la demo publica queda endurecida, pero el equipo puede usar Boost en desarrollo.

## Comandos base

Ver comandos disponibles:

```bash
php artisan list boost --env=local
```

Instalar o re-sincronizar Boost:

```bash
php artisan boost:install --guidelines --skills --mcp --env=local
```

Actualizar guidelines y skills cuando cambie Boost:

```bash
php artisan boost:update --env=local
```

Arrancar el servidor MCP manualmente, normalmente lo hace el agente desde `.mcp.json`:

```bash
php artisan boost:mcp --env=local
```

## Como debe usarlo Codex

Cuando se solicite un cambio en este proyecto, Codex debe seguir este orden:

1. Leer este documento y la documentacion del proyecto en `Documentacion/`.
2. Usar Laravel Boost si el MCP esta disponible en la sesion.
3. Si el MCP no esta expuesto, usar los comandos Laravel equivalentes y dejar claro el fallback.
4. Inspeccionar rutas, schema, config y logs antes de asumir comportamiento.
5. Revisar documentacion Laravel/package-aware antes de implementar APIs delicadas.
6. Implementar cambios pequenos, coherentes con la arquitectura existente.
7. Validar con comandos reales antes de cerrar.

## Herramientas Boost disponibles

En la version instalada (`laravel/boost v2.4.3`), Boost expone estas herramientas MCP:

- `application-info`: entender version de Laravel, PHP, paquetes y contexto de app.
- `database-connections`: listar conexiones configuradas.
- `database-schema`: revisar tablas, columnas, indices y relaciones antes de crear o modificar modelos.
- `database-query`: ejecutar consultas de lectura controladas contra la base de datos.
- `get-absolute-url`: resolver la URL correcta de una ruta o path del proyecto.
- `last-error`: leer el ultimo error registrado por Laravel.
- `read-log-entries`: revisar entradas recientes de logs.
- `browser-logs`: leer errores recientes del navegador capturados por Boost.
- `search-docs`: consultar documentacion oficial ajustada a las versiones instaladas.

Si se necesita inspeccionar rutas, config o ejecutar pruebas puntuales y esas herramientas no aparecen disponibles directamente en Codex, usar equivalentes locales:

```bash
php artisan about
php artisan route:list -vv
php artisan db:table pherce_intel.inventory_products
php artisan migrate:status
php artisan config:show cache
php artisan tinker --execute="..."
```

## Reglas para este proyecto

### Seguridad

- No activar `APP_DEBUG=true` para la demo publica.
- No cambiar `APP_ENV=production` solo para que aparezcan comandos de Boost.
- Para Boost usar `--env=local`.
- No subir `.env` ni tokens reales a Git.
- No exponer Horizon, Pulse, Telescope ni Boost por el tunel publico.
- Si se toca integraciones externas, mover credenciales a `.env` y leerlas desde `config/*`.

### Base de datos

- El schema operativo propio es `pherce_intel`.
- `tini_raw` es fuente externa/raw y debe mantenerse de solo lectura para la app.
- Los datos por sucursal deben usar `branch_id`.
- En inventario, el stock es independiente por sucursal. No mezclar stock de matriz con otras sucursales.
- Para productos, la identidad correcta es `branch_id + code`.
- Para cargas grandes, usar chunks y `upsert`, no inserts uno por uno.

### Escalabilidad

- No cargar miles de registros completos en Inertia.
- Usar paginacion server-side, normalmente `25`, `50` o `100`.
- Usar busqueda en servidor por codigo/nombre.
- Cachear agregados o KPIs con Redis cuando cambian poco.
- Invalidar cache despues de imports o cambios de inventario.

### Laravel/Inertia

- Controladores delgados.
- Logica de negocio en `app/Domain/*/Services`.
- Modelos con escritura de negocio deben usar `Auditable`.
- Modelos por sucursal deben usar `BranchScoped` o autorizacion equivalente.
- Validar requests antes de consultar.
- Usar nombres de rutas y middleware existentes.

## Flujo recomendado antes de cada cambio

Ejecutar o pedir a Codex que ejecute:

```bash
php artisan about --env=local
php artisan route:list -vv --env=local
php artisan migrate:status --env=local
```

Si se va a tocar base de datos:

```bash
php artisan db:table nombre_tabla
```

Si se va a tocar frontend:

```bash
npm run types:check
npm run lint:check
npm run build
```

Si se va a tocar PHP:

```bash
vendor/bin/pint --test
composer validate --no-check-publish
```

## Flujo recomendado despues de cada cambio

Minimo:

```bash
vendor/bin/pint --test
npm run lint:check
npm run types:check
npm run build
php artisan route:list -vv
```

Para demo por Cloudflare Tunnel:

```bash
php artisan optimize
```

Y luego probar rutas criticas por navegador o smoke HTTP:

- Login
- Dashboard
- Inventario productos
- Compras
- Recepcion
- Logistica
- Equipo

## Como pedirle trabajo a Codex

Puedes iniciar una sesion diciendo:

> Lee `Documentacion/laravel-boost.md`, usa Laravel Boost o sus equivalentes locales, revisa schema/rutas/config/logs antes de tocar codigo y aplica las reglas del proyecto.

Para cambios de inventario:

> Apoyate en `Documentacion/laravel-boost.md`. Recuerda que el stock es por sucursal, matriz es `branch_id=1`, no cargues todos los productos en Inertia y usa Redis para KPIs.

Para seguridad/demo:

> Apoyate en Boost. Mantener `APP_ENV=production`, `APP_DEBUG=false`, no exponer herramientas internas y validar login por `https://appcomsafran.cloud`.

## Notas actuales

- Boost esta instalado, pero con la demo en modo production los comandos aparecen usando `--env=local`.
- La configuracion MCP ya apunta a `php artisan boost:mcp --env=local`.
- Si el agente no ve el MCP de Boost directamente, debe usar Artisan como fallback y decirlo explicitamente.
- La documentacion oficial de Boost debe ser la fuente principal cuando haya duda sobre capacidades o comandos.
