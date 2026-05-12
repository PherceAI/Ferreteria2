# Revision de arquitectura y limpieza

Fecha: 2026-05-12

## Alcance validado

- Laravel Boost esta instalado y configurado para MCP con `php artisan boost:mcp --env=local`.
- En esta sesion no hubo namespace MCP directo de Boost; se uso el fallback documentado del proyecto con Artisan local.
- Comandos ejecutados: `php artisan about --env=local`, `php artisan list boost --env=local`, `php artisan route:list -vv --env=local`, `php artisan migrate:status --env=local`, `php artisan db:table ...` y `php artisan model:show ...`.
- Rutas principales protegidas con autenticacion, verificacion de email y `RequireActiveBranch` donde aplica. `/up` esta disponible para health checks.

## Estructura actual

- La app sigue una separacion sana por dominios en `app/Domain`: inventario, compras, warehouse/traspasos, dashboard, logistica y notificaciones.
- Los controladores de flujos criticos delegan la mayor parte de la regla de negocio a servicios de dominio.
- Las tablas operativas usan PostgreSQL con indices compuestos adecuados para busquedas por sucursal, estado, producto, proveedor, recepcion y traspasos.
- Los modelos operativos relevantes tienen auditoria o scopes/validaciones explicitas por sucursal.

## Hallazgos corregidos

- Se centralizaron roles operativos en `config/internal.php` para evitar listas duplicadas en controladores, servicios y soporte.
- `User::hasGlobalBranchAccess()` ahora usa `internal.owner_roles`, evitando literales repetidos de roles globales.
- Las reglas de autorizacion de traspasos quedaron concentradas en `BranchTransferService`: crear, preparar, recibir, completar TINI, cancelar y ver.
- `BranchTransferController` ya no replica reglas de permisos; solo valida request, autoriza via servicio y delega workflow.
- Las etiquetas base de estados de traspaso quedaron en `BranchTransfer`, evitando que cada pantalla reconstruya el mismo catalogo.
- Se reemplazaron referencias mojibakeadas de `Dueño` en reglas de traspasos por configuracion centralizada.

## Riesgos observados

- El log historico contiene errores de Boost ejecutado sin entorno local y timeouts de `queue:listen`; no aparecen como bloqueantes del codigo actual, pero en produccion debe usarse `queue:work`/Horizon bajo Supervisor.
- Algunas pantallas todavia contienen formateo de datos Inertia dentro de controladores. Es aceptable por ahora, pero si crecen deberian moverse a presenters/resources internos.
- `withoutBranchScope()` esta presente en consultas agregadas o de cross-branch. Su uso actual debe mantenerse revisado cuando se agreguen nuevas pantallas.

## Reglas de continuidad

- No duplicar roles en codigo; agregar nuevas capacidades en `config/internal.php`.
- Los flujos con transiciones deben vivir en servicios de dominio y usar transacciones/bloqueos cuando cambien estado.
- Los controladores deben limitarse a validar, autorizar, delegar y renderizar.
- No exponer Boost, Telescope, Horizon o Pulse sin gates de usuario interno.
- Antes de produccion, repetir esta revision con pruebas completas y smoke test en Coolify.
