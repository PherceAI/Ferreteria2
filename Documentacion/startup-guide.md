# Guía de Inicio del Proyecto — Panel Pherce (Ferreteria)

## Prerrequisitos

Antes de iniciar, asegúrate de tener instalado y funcionando:

| Herramienta         | Versión mínima | Para qué se usa                                    |
| ------------------- | -------------- | -------------------------------------------------- |
| **Laravel Herd**    | —              | Sirve PHP + Nginx localmente (dominios `.test`)     |
| **Docker Desktop**  | —              | Contenedores de PostgreSQL y Redis                  |
| **Node.js + npm**   | Node 20+       | Compilación del frontend (Vite + React)             |
| **Composer**        | 2.x            | Dependencias PHP                                    |
| **cloudflared**     | —              | Túnel Cloudflare para exponer la app a internet     |

---

## Secuencia de Inicio (en orden estricto)

### Paso 1 — Levantar los contenedores Docker

PostgreSQL y Redis corren en Docker. **Deben estar activos antes que cualquier otra cosa.**

```powershell
docker compose -f docker/compose.yml up -d
```

**Verificar que estén healthy:**

```powershell
docker ps --format "table {{.Names}}\t{{.Status}}"
```

Deberías ver:

```
NAMES                 STATUS
ferreteria_postgres   Up X minutes (healthy)
ferreteria_redis      Up X minutes (healthy)
```

> [!WARNING]
> Si Postgres o Redis no están corriendo, la app fallará con errores de conexión a base de datos o sesiones. **Siempre levanta Docker primero.**

---

### Paso 2 — Confirmar que Laravel Herd está sirviendo el sitio

Laravel Herd sirve la aplicación en el dominio local `http://ferreteria.test`. Para verificar:

1. Abre tu navegador y visita `http://ferreteria.test`
2. Deberías ver la página de bienvenida o el login

> [!NOTE]
> Herd resuelve el dominio `.test` automáticamente si la carpeta del proyecto está linkeada. Si ves un 404 de Herd ("Site not found"), asegúrate de que la carpeta `Ferreteria/app` esté registrada en Herd como sitio activo.

---

### Paso 3 — Compilar los assets del frontend

Este es el paso más crítico y donde ocurren la mayoría de errores. Hay **dos modos** de trabajo:

#### Modo A — Desarrollo local (solo tú, en tu navegador)

Usa el servidor de desarrollo de Vite para hot reload:

```powershell
npm run dev
```

Esto crea un archivo `public/hot` que le dice a Laravel: *"carga los assets desde `localhost:5173`"*.

> [!IMPORTANT]
> Este modo **solo funciona cuando accedes desde tu propia máquina** (`http://ferreteria.test`). Si compartes la URL del túnel de Cloudflare con alguien, esa persona verá una **página en blanco** porque su navegador no puede acceder a tu `localhost:5173`.

#### Modo B — Compartir vía Cloudflare Tunnel (otras personas van a acceder)

Compila los assets para producción:

```powershell
npm run build
```

Después del build, **verifica que NO exista el archivo `public/hot`:**

```powershell
# Verificar
if (Test-Path public\hot) { Write-Host "⚠️  ARCHIVO HOT DETECTADO — eliminándolo..." -ForegroundColor Yellow; Remove-Item public\hot -Force } else { Write-Host "✅ OK — no hay archivo hot" -ForegroundColor Green }
```

> [!CAUTION]
> **Esta es la causa #1 de páginas en blanco.** Si corres `npm run dev` y luego lo detienes con Ctrl+C, el archivo `public/hot` puede quedar persistido. Laravel seguirá intentando cargar assets desde `localhost:5173` (que ya no existe), resultando en una página completamente vacía. **Siempre elimina `public/hot` antes de usar el túnel.**

---

### Paso 4 — Iniciar los servicios de Laravel en background

```powershell
# WebSockets (Reverb)
php artisan reverb:start

# Cola de trabajos
php artisan queue:listen --tries=1
```

> [!NOTE]
> Estos servicios son necesarios para notificaciones en tiempo real y procesamiento de tareas en background. La app funciona sin ellos, pero las alertas y WebSockets no estarán activos.

---

### Paso 5 — Iniciar el túnel de Cloudflare

```powershell
cloudflared tunnel run --url http://ferreteria.test --http-host-header ferreteria.test ferreteria-tunnel
```

**Los dos flags son obligatorios:**

| Flag                    | Por qué es necesario                                                                 |
| ----------------------- | ------------------------------------------------------------------------------------- |
| `--url`                 | Le dice a cloudflared hacia dónde reenviar el tráfico (Herd escucha en puerto 80)     |
| `--http-host-header`    | Reescribe el header `Host` para que Herd identifique qué sitio servir                 |

**Verificar que el túnel conectó correctamente:**

Busca en la salida del comando líneas como:

```
INF Registered tunnel connection connIndex=0 ... location=mia05 protocol=quic
INF Registered tunnel connection connIndex=1 ... location=mia02 protocol=quic
```

Deberías ver 4 conexiones registradas. Si ves `WRN No ingress rules`, el túnel no sabe a dónde enviar tráfico y devolverá error 503.

---

## Variable de entorno crítica: `ASSET_URL`

Para que los assets (CSS, JS) funcionen a través del túnel, el archivo `.env` **debe** tener:

```env
APP_URL=https://appcomsafran.cloud
ASSET_URL=https://appcomsafran.cloud
```

**¿Por qué?** Sin `ASSET_URL`, Laravel genera las URLs de los assets basándose en el header `Host` de la request entrante. Como el túnel reescribe ese header a `ferreteria.test`, los assets terminan con URLs como `http://ferreteria.test/build/assets/app.js`. Un navegador externo no puede resolver `ferreteria.test`, así que los assets nunca cargan.

Con `ASSET_URL` definido, Laravel **siempre** genera las URLs apuntando al dominio público del túnel, independientemente del host header.

---

## Resumen: Comando rápido de inicio completo

Para copiar y pegar cuando necesites levantar todo desde cero:

```powershell
# 1. Docker (Postgres + Redis)
docker compose -f docker/compose.yml up -d

# 2. Compilar assets (modo túnel)
npm run build
if (Test-Path public\hot) { Remove-Item public\hot -Force }

# 3. Limpiar caches de Laravel
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Servicios background (cada uno en su propia terminal)
php artisan reverb:start
php artisan queue:listen --tries=1

# 5. Túnel Cloudflare (en su propia terminal)
cloudflared tunnel run --url http://ferreteria.test --http-host-header ferreteria.test ferreteria-tunnel
```

---

## Troubleshooting rápido

### Página en blanco (solo se ve el fondo)

| Causa | Solución |
| ----- | -------- |
| Archivo `public/hot` existe | `Remove-Item public\hot -Force` y luego `npm run build` |
| Assets apuntan a `ferreteria.test` | Verificar que `ASSET_URL=https://appcomsafran.cloud` está en `.env` |
| Cache de Laravel obsoleto | `php artisan config:clear` |

### Error 1033 — Cloudflare Tunnel error

| Causa | Solución |
| ----- | -------- |
| Túnel no está corriendo | Ejecutar `cloudflared tunnel run ...` con los flags correctos |
| cloudflared sin reglas de ingress | Asegurarse de pasar `--url http://ferreteria.test` |

### Error 404 — Herd "Site not found"

| Causa | Solución |
| ----- | -------- |
| Host header incorrecto | Asegurarse de pasar `--http-host-header ferreteria.test` al túnel |
| Sitio no registrado en Herd | Linkear la carpeta del proyecto en Herd |

### Error 500 — Error interno de Laravel

| Causa | Solución |
| ----- | -------- |
| Postgres no está corriendo | `docker compose -f docker/compose.yml up -d` |
| Redis no está corriendo | Mismo comando anterior |
| `.env` corrupto o faltante | Copiar desde `.env.example` y configurar |

### Assets sin estilos (HTML plano gigante con texto "Laravel")

| Causa | Solución |
| ----- | -------- |
| Vite dev server corriendo en vez de build | Detener `npm run dev`, ejecutar `npm run build`, eliminar `public/hot` |

---

## Diferencia entre modos de trabajo

```
┌─────────────────────────────────────────────────────────────────┐
│  MODO DESARROLLO LOCAL (npm run dev)                            │
│                                                                 │
│  Tú → http://ferreteria.test → Herd → Laravel                  │
│                                    ↓                            │
│                              Assets desde                       │
│                           localhost:5173 (Vite)                  │
│                                                                 │
│  ✅ Hot reload instantáneo                                      │
│  ❌ Solo funciona en tu máquina                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  MODO TÚNEL CLOUDFLARE (npm run build)                          │
│                                                                 │
│  Internet → appcomsafran.cloud → Cloudflare → Tunnel            │
│                                                    ↓            │
│                                         ferreteria.test (Herd)  │
│                                                    ↓            │
│                                               Laravel           │
│                                                    ↓            │
│                                         Assets desde            │
│                                    appcomsafran.cloud/build/    │
│                                                                 │
│  ✅ Cualquier persona puede acceder                             │
│  ❌ Sin hot reload (hay que re-compilar con npm run build)      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Checklist pre-compartir

Antes de enviar el link `https://appcomsafran.cloud` a alguien, verifica:

- [ ] Docker corriendo (`docker ps` muestra postgres y redis healthy)
- [ ] `npm run build` ejecutado (assets compilados en `public/build/`)
- [ ] Archivo `public/hot` **NO existe**
- [ ] `ASSET_URL=https://appcomsafran.cloud` en `.env`
- [ ] `php artisan config:clear` ejecutado después de cambios en `.env`
- [ ] Túnel cloudflared corriendo con ambos flags (`--url` y `--http-host-header`)
- [ ] Al menos 4 conexiones registradas en la salida de cloudflared
