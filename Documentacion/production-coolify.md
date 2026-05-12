# Despliegue en Coolify

## Estrategia

La aplicacion se despliega como un solo recurso Laravel/Inertia usando Nixpacks. PostgreSQL y Redis se crean como recursos separados de Coolify y se conectan por variables de entorno.

## Recursos

1. Crear una base PostgreSQL en Coolify.
2. Crear un Redis en Coolify.
3. Crear la aplicacion desde el repositorio y seleccionar `nixpacks`.
4. Configurar `Ports Exposes` en `80`.
5. Copiar `.env.coolify.example` como guia en las variables del recurso, reemplazando secretos, dominio y hosts internos de PostgreSQL/Redis.

## Variables criticas

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` y `ASSET_URL` con el dominio HTTPS real.
- `ALLOW_PUBLIC_REGISTRATION=false`
- `SESSION_SECURE_COOKIE=true`
- `TRUSTED_PROXIES=*` si Coolify/Traefik es el unico punto de entrada.
- `RUN_MIGRATIONS=true` para que el contenedor ejecute migraciones en el arranque.
- `QUEUE_WORKER=queue` para iniciar `queue:work`; cambiar a `horizon` solo si se va a operar Horizon.
- `SCHEDULER_ENABLED=true` para Gmail, flota, Horizon snapshots y backups.
- `REVERB_ENABLED=false` por defecto. Activarlo requiere publicar WebSockets correctamente en Coolify.

## Post-deploy

El `nixpacks.toml` ejecuta:

```bash
php artisan storage:link
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Luego Supervisor mantiene activos Nginx, PHP-FPM, cola, scheduler y opcionalmente Reverb.

## Checklist de salida

- `/up` responde 200.
- Login funciona con un usuario creado por administracion.
- Registro publico no existe.
- Dashboard carga datos reales sin mocks.
- Inventario, compras, recepcion y traspasos abren sin errores.
- Redis atiende cache, sesiones y cola.
- Hay backup de PostgreSQL configurado y una restauracion probada en staging.
- Pulse/Horizon/Telescope solo son accesibles por correos o roles de observabilidad.
