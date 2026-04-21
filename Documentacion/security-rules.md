# security-rules.md — Cifrado, Auditoría y Prohibiciones

## Principio: cumplimiento LOPDP Ecuador

La Ley Orgánica de Protección de Datos Personales de Ecuador exige protección de datos personales y trazabilidad de acceso. Este sistema cumple mediante cifrado en reposo, auditoría completa y control de acceso granular.

---

## Qué se cifra (trait Encryptable)

Los siguientes campos se cifran en reposo usando el trait `Encryptable` de Laravel (AES-256-CBC con clave en `APP_KEY`):

| Entidad             | Campos cifrados                                         |
| ------------------- | ------------------------------------------------------- |
| Proveedores         | RUC, dirección fiscal, datos bancarios, contacto directo |
| Empleados/Usuarios  | Cédula, teléfono personal, dirección domiciliaria       |
| Clientes (si aplica)| RUC/cédula, dirección, datos de contacto                |

**Regla:** las claves de cifrado viven en variables de entorno (`.env`). Nunca en código, nunca en base de datos, nunca en repositorio.

**Consecuencia para queries:** los campos cifrados NO son buscables por SQL directo. Si se necesita buscar por RUC, se almacena un hash adicional (campo `ruc_hash`) para lookup, manteniendo el valor cifrado en el campo principal.

---

## Qué se audita (trait Auditable + Spatie Activity Log)

**Toda escritura en `pherce_intel`** genera un registro de auditoría. Cada entrada incluye:

- `causer`: usuario que realizó la acción (ID + nombre)
- `subject`: modelo afectado (tipo + ID)
- `properties.old`: valores anteriores (en UPDATE/DELETE)
- `properties.attributes`: valores nuevos (en CREATE/UPDATE)
- `description`: acción realizada (created, updated, deleted)
- Timestamp automático
- Branch ID del contexto activo

**Los logs de auditoría son inmutables.** Nunca se borran, nunca se modifican, nunca se expurgan. Ni por migración, ni por seeder, ni por comando artisan.

**Acciones que generan log adicional** (más allá de CRUD):
- Login exitoso y fallido
- Cambio de sucursal activa
- Ejecución de ETL (inicio, fin, errores)
- Acceso a datos cifrados (descifrado para visualización)
- Exportación de datos o reportes

---

## Lo que el agente NUNCA debe generar

1. **Nunca hardcodear credenciales** — ni passwords, ni API keys, ni tokens en código fuente
2. **Nunca loguear datos sensibles** — no imprimir en logs: passwords, RUC, cédulas, datos bancarios
3. **Nunca exponer stack traces en producción** — `APP_DEBUG=false` siempre en producción
4. **Nunca desactivar CSRF** — Inertia lo maneja automáticamente, no crear excepciones
5. **Nunca crear endpoints sin autenticación** — todo pasa por middleware `auth`
6. **Nunca crear endpoints sin autorización** — toda ruta verifica permisos con Policies
7. **Nunca crear queries raw sin parameter binding** — prevención de SQL injection
8. **Nunca almacenar passwords en texto plano** — bcrypt vía Fortify (BCRYPT_ROUNDS=12), siempre
9. **Nunca crear rutas fuera de web middleware group** — no hay API pública
10. **Nunca generar migrations que hagan DELETE en `activity_log`** — es inmutable

---

## Configuración de seguridad (infraestructura)

- **HTTPS obligatorio** — Nginx + Let's Encrypt. Redirect HTTP→HTTPS.
- **Rate limiting** — en todos los endpoints, especialmente login (máx. 5 intentos/minuto)
- **Sesiones** — almacenadas en Redis, con timeout configurable
- **CORS** — no aplica (no hay API externa, todo es Inertia mismo dominio)
- **Headers de seguridad** — X-Frame-Options, X-Content-Type-Options, Strict-Transport-Security

---

## Backups (Spatie Backup)

- Backup automático diario de PostgreSQL + archivos críticos
- Almacenamiento en disco separado del VPS (configurar destino externo)
- Retención mínima: 30 días de backups
- Notificación por email si un backup falla

<!-- PENDIENTE: Definir destino externo de backups (S3, otro VPS, storage local adicional). -->
