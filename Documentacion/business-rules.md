# business-rules.md — Reglas de Dominio Transversales

## Regla de oro del sistema

Si TINI ya gestiona algo → el sistema lo lee de TINI vía ETL. Nunca recrear.
Si TINI no puede gestionarlo → el sistema lo gestiona exclusivamente. Nunca duplicar entrada de datos.

---

## Scope por sucursal

**Todo dato operativo está vinculado a una sucursal.** No existen datos operativos "sin sucursal" excepto configuración global.

- Al iniciar sesión, el usuario selecciona su sucursal activa
- El middleware `BranchScope` inyecta `branch_id` en cada request
- Todo model con trait `BranchScoped` filtra automáticamente por `branch_id`
- El **Dueño** puede ver y operar en todas las sucursales. Puede cambiar de sucursal activa sin cerrar sesión
- La **Contadora** y la **Encargada de Compras** tienen acceso a las 4 sucursales desde la sede
- Los vendedores y bodegueros operan en su sucursal asignada

**Datos que NO se filtran por sucursal:** usuarios, roles, configuración global, catálogo de productos (TINI lo maneja como catálogo único), proveedores.

**Datos que SÍ se filtran por sucursal:** stock, alertas, recepciones, transferencias, umbrales de stock mínimo, tracking de caducidad.

---

## Flujo de factura de compra (recepción de mercadería)

Este es el flujo más crítico del sistema. Resuelve la raíz #1 (inventario no confiable).

### Dos canales de entrada
1. **Electrónica:** llega por email (XML/PDF). La encargada de compras la procesa desde sede.
2. **Física:** llega con la mercadería. El bodeguero la recibe en la sucursal.

### Flujo completo
1. Factura ingresa al sistema (email interceptado o registro manual por compras)
2. Sistema crea una `reception_confirmation` en estado `pending`
3. Se notifica al bodeguero de la sucursal correspondiente
4. Bodeguero verifica físicamente la mercadería contra la factura
5. Bodeguero confirma recepción línea por línea (`reception_confirmation_items`)
6. Si hay discrepancias (cantidad recibida ≠ facturada), se marca `has_discrepancy`
7. Solo mercadería confirmada se considera "stock disponible" para ventas
8. TINI registra la factura por su lado (el personal la ingresa en TINI normalmente)
9. El ETL sincroniza el registro de TINI → `tini_raw`
10. El sistema cruza: confirmación de bodega + registro TINI = stock real validado

**Regla clave:** hasta que bodega confirme, el stock de esa factura NO se refleja como disponible en el sistema, aunque TINI ya lo haya registrado.

---

## Stock: tres estados operativos

TINI solo conoce un número: "stock". El sistema desglosa en tres estados:

| Estado        | Significado                                              | Fuente                    |
| ------------- | -------------------------------------------------------- | ------------------------- |
| Registrado    | TINI dice que está (factura ingresada en TINI)           | `tini_raw` vía ETL       |
| Confirmado    | Bodega verificó físicamente que llegó                    | `pherce_intel` (confirmaciones) |
| Disponible    | Confirmado + no reservado + no en tránsito               | Calculado (cruce de datos) |

**Para ventas:** solo se muestra stock **disponible**. Nunca el registrado sin confirmar.

---

## Alertas: quién recibe qué

| Tipo de alerta           | Destinatarios              | Severidad base |
| ------------------------ | -------------------------- | -------------- |
| Stock bajo (bajo mínimo) | Compras + Bodega           | warning        |
| Producto próximo a caducar | Bodega                   | warning        |
| Producto caducado        | Bodega + Compras           | critical       |
| Descuadre contable detectado | Contadora              | critical       |
| Factura pendiente de confirmar (>24h) | Bodega + Compras | warning     |
| Transferencia pendiente de recepción | Bodega destino    | info          |

Las alertas se configuran via `alert_subscriptions`. El sistema las genera automáticamente por Jobs programados.

---

## Transferencias entre sucursales

1. Usuario autorizado inicia transferencia desde sucursal origen
2. Se registra `branch_transfer` con estado `initiated` y detalle de productos
3. Se notifica a la sucursal destino
4. Mercadería sale de origen → estado cambia a `in_transit`
5. Bodeguero de destino confirma recepción → estado `received`
6. Si hay discrepancias, se registran en `received_qty` vs `quantity`

**Regla:** mercadería en tránsito no se cuenta como stock disponible en ninguna sucursal.

<!-- PENDIENTE: Definir quién puede autorizar transferencias (¿solo dueño? ¿jefe de sucursal? ¿compras?). -->

---

## Ventas y crédito

- TINI gestiona las ventas. El sistema las lee vía ETL.
- El sistema agrega validación previa: antes de vender, el vendedor puede consultar stock **disponible** (no el de TINI, sino el calculado con confirmaciones).
- Hay ventas a contado y a crédito. TINI registra ambas.

<!-- PENDIENTE: Reglas de crédito (límites, bloqueos automáticos, aging de cartera). Se definirán en Fase 3. -->

---

## Roles y permisos

Roles definidos (gestionados por Spatie Permission):

| Rol               | Sucursales          | Alcance                                           |
| ----------------- | ------------------- | ------------------------------------------------- |
| Dueño             | Todas               | Acceso total. Ve todo. Configura todo.             |
| Contadora         | Todas               | Contabilidad, conciliaciones, reportes financieros |
| Encargada Compras | Todas               | Facturas de compra, proveedores, órdenes, alertas  |
| Bodeguero         | Su sucursal asignada | Confirmar recepciones, transferencias, caducidad   |
| Vendedor          | Su sucursal asignada | Consultar stock, ver alertas de su sucursal        |

<!-- PENDIENTE: Matriz granular de permisos por módulo (quién puede ver costos, quién modifica precios, quién anula). Se definirá cuando se construya cada módulo. -->

---

## Reglas transversales

1. **Toda escritura en `pherce_intel` genera log de auditoría.** Sin excepciones. Incluye: usuario, sucursal, tabla, valores antes/después, timestamp.
2. **No hay eliminación física (DELETE).** Se desactivan registros con `is_active = false` o se cambia `status`.
3. **Timestamps siempre en UTC.** El frontend convierte a zona horaria local (Ecuador: UTC-5).
4. **IDs de TINI son strings.** Nunca asumir que son numéricos. Siempre VARCHAR(50).
5. **Moneda: USD.** Ecuador usa dólar americano. No hay conversiones de moneda.
6. **Decimales: 2 posiciones** para cantidades monetarias. Hasta 4 para cantidades de inventario si el negocio lo requiere.
