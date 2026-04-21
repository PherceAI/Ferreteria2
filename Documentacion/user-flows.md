# user-flows.md — Flujos Críticos del Sistema

## Convención de este documento

Cada flujo es una lista numerada que describe la secuencia completa desde la acción del usuario hasta el resultado final. El agente usa estos flujos para entender navegación, transiciones de estado y qué módulos intervienen.

---

## Flujo 1: Login + Selección de Sucursal

1. Usuario accede a la URL del sistema
2. Pantalla de login (Fortify): email + password; 2FA opcional vía TOTP + recovery codes
3. Autenticación exitosa → sistema consulta `user_branch` para obtener sucursales asignadas
4. Si el usuario tiene **una sola sucursal**: se selecciona automáticamente, va al dashboard
5. Si el usuario tiene **múltiples sucursales**: pantalla de selección de sucursal
6. Usuario selecciona sucursal → se guarda en sesión (`active_branch_id` en `users`)
7. Middleware `BranchScope` inyecta `branch_id` en cada request subsiguiente
8. Redirect al dashboard de la sucursal seleccionada
9. El usuario puede **cambiar de sucursal** desde el navbar sin cerrar sesión (solo si tiene acceso a más de una)

**Módulos involucrados:** Auth, Branches

---

## Flujo 2: Recepción de Factura de Compra (electrónica)

1. Proveedor envía factura electrónica por email (XML/PDF)
2. Sistema intercepta el email y extrae datos de la factura
3. Se crea `reception_confirmation` en estado `pending` vinculada a la sucursal correspondiente
4. Se notifica al bodeguero de esa sucursal (alerta web via Reverb + Echo)
5. Se notifica a la encargada de compras (alerta web)
6. Bodeguero ve la tarea pendiente en su dashboard de bodega
7. Continúa en → **Flujo 3: Confirmación de recepción**

**Módulos involucrados:** Purchasing, Notifications, Warehouse

<!-- PENDIENTE: Definir la integración de lectura de email (IMAP, webhook de proveedor, ingreso manual con upload de XML/PDF). Fase 2. -->

---

## Flujo 3: Confirmación de Recepción en Bodega

1. Bodeguero abre la recepción pendiente desde su panel
2. Ve la lista de productos con cantidades esperadas (de la factura)
3. Para cada línea, ingresa la cantidad realmente recibida (`received_qty`)
4. Si `received_qty ≠ expected_qty` → sistema marca `has_discrepancy = true`
5. Bodeguero puede agregar notas en caso de discrepancia
6. Bodeguero confirma la recepción → estado cambia a `confirmed` (o `discrepancy`)
7. Sistema dispara evento `ReceptionConfirmed`
8. El stock confirmado se refleja como **disponible** para consulta de vendedores
9. Si hay discrepancias → se genera alerta para compras
10. En paralelo, el personal ingresa la factura en TINI normalmente
11. El ETL sincroniza el registro de TINI a `tini_raw` en su próxima ejecución
12. El sistema ahora puede cruzar: confirmación de bodega + dato TINI = stock validado

**Módulos involucrados:** Warehouse, Inventory, Notifications, EtlBridge

---

## Flujo 4: Alerta de Stock Bajo

1. Job programado (`CheckStockThresholds`) se ejecuta periódicamente
2. Para cada sucursal, compara stock disponible vs. `stock_thresholds.minimum_qty`
3. Si stock disponible < mínimo → genera alerta tipo `stock_low`
4. Consulta `alert_subscriptions` para determinar destinatarios
5. Crea registro en tabla `alerts`
6. Envía notificación en tiempo real vía Reverb a los canales de sucursal correspondientes
7. Usuarios suscritos ven la alerta en su panel de notificaciones
8. Encargada de compras ve alertas consolidadas de todas las sucursales
9. Cuando el stock se repone (nueva recepción confirmada), el sistema puede marcar la alerta como `is_resolved`

**Módulos involucrados:** Notifications, Inventory, Purchasing

---

## Flujo 5: Transferencia entre Sucursales

1. Usuario autorizado inicia transferencia desde la sucursal origen
2. Selecciona sucursal destino y productos con cantidades
3. Sistema crea `branch_transfer` con estado `initiated` y sus `branch_transfer_items`
4. Se notifica a la sucursal destino
5. Mercadería sale físicamente → usuario marca como `in_transit`
6. Stock de esos productos en origen se reduce (o se marca en tránsito)
7. Bodeguero de sucursal destino recibe notificación
8. Bodeguero confirma recepción: ingresa `received_qty` por cada item
9. Si todo coincide → estado `received`. Si hay discrepancia → se registra.
10. Stock de destino se actualiza con la mercadería recibida

**Regla:** mercadería en tránsito no cuenta como stock disponible en ninguna sucursal.

**Módulos involucrados:** Warehouse, Inventory, Notifications, Branches

<!-- PENDIENTE: Definir quién autoriza transferencias y si requiere aprobación previa del dueño. -->

---

## Flujo 6: Consulta de Stock por Vendedor

1. Vendedor accede a la sección de inventario de su sucursal
2. Ve el listado de productos con stock **disponible** (no el de TINI, sino el calculado)
3. Puede buscar por nombre, código o categoría
4. Para cada producto ve: stock disponible, stock registrado (TINI), pendiente de confirmar
5. Si un producto está bajo mínimo, ve indicador visual (badge o color)
6. Si un producto está próximo a caducar, ve indicador visual
7. El vendedor NO puede modificar stock, umbrales ni confirmaciones
8. El vendedor puede ver precios (los que TINI maneja)

**Módulos involucrados:** Inventory, Sales

---

## Flujos futuros (no construir aún)

Los siguientes flujos se construirán en fases posteriores. No generar código para ellos hasta que se soliciten:

- **Generación automática de proformas** (Fase 3 — OCR + IA)
- **Control automático de crédito** con bloqueos (Fase 3)
- **Dashboard gerencial de rentabilidad** por producto/vendedor/sucursal (Fase 4)
- **Compras predictivas** basadas en rotación histórica (Fase 4)
- **Conciliación contable automatizada** (Fase 2-3)
