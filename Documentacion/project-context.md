# project-context.md — Cliente, Problema y Restricciones

## El cliente

**Comercial San Francisco** — ferretería con 4 sucursales en Ecuador.
- 3 sucursales en Riobamba (una es la sede principal)
- 1 sucursal en Macas
- ~15 usuarios totales del sistema

**Estructura organizacional relevante:**
- Una sola contadora para las 4 sucursales (trabaja desde sede principal)
- Una sola encargada de compras para las 4 sucursales (trabaja desde sede principal)
- Cada sucursal tiene sus propios vendedores y bodegueros
- Inventario físicamente independiente por sucursal
- Todas las sucursales usan el mismo servidor TINI en la sede principal

---

## El ERP actual: TINI

TINI es un sistema COBOL legacy que corre en un servidor físico en la sucursal principal.
Almacena datos en archivos `.dat` de texto plano. Todas las sucursales se conectan al mismo TINI.

**Lo que TINI hace:** registra transacciones (ventas, compras, movimientos de inventario), gestiona facturación, lleva contabilidad básica, descuenta stock al vender.

**Lo que TINI NO hace:** alertar, prevenir, cruzar datos entre áreas, manejar caducidades, manejar stock mínimo, validar automáticamente, reportar proactivamente.

**Restricción inamovible:** TINI no se modifica, no se reemplaza, no se le escribe. Es de solo lectura para nuestro sistema. El personal sigue usando TINI para sus transacciones diarias normalmente.

<!-- PENDIENTE: Estructura exacta de los archivos .dat (formato, campos, encoding, separación por sucursal). Se definirá cuando se explore el servidor TINI. -->

---

## Las 3 raíces sistémicas del problema

**1. Inventario no confiable.** El stock en sistema no coincide con la realidad física. Vendedores y bodega asumen que los datos pueden estar mal. Hay "facturas en pendiente" que distorsionan el stock disponible. Se requieren conteos físicos constantes.

**2. Sin estado compartido.** Los procesos viven en la memoria del personal, en papel y en WhatsApp. No hay flujos definidos con visibilidad entre áreas. Cada departamento opera aislado.

**3. Sistema mudo y reactivo.** TINI guarda datos pero nunca alerta. Los errores (descuadres, límites superados, vencimientos) se descubren tarde y por accidente.

---

## Qué construimos y qué NO

**Construimos:** una capa inteligente que trabaja encima de TINI. Lee sus datos, los cruza, los enriquece con información que TINI no maneja, y genera alertas, validaciones y dashboards proactivos.

**NO construimos:** un reemplazo de TINI. El personal sigue transaccionando en TINI. Nuestro sistema observa, interpreta y actúa sobre esos datos.

**Regla de alcance:** si TINI ya gestiona algo → lo leemos de TINI vía ETL. Si TINI no puede gestionarlo → nuestro sistema lo gestiona exclusivamente. Nunca se duplica.

---

## Áreas de fricción diagnosticadas (por severidad)

| Área         | Fricción | Problemas clave                                              |
| ------------ | -------- | ------------------------------------------------------------ |
| Contabilidad | 9/10     | Búsqueda manual de errores, conciliaciones manuales          |
| Inventario   | 9/10     | Stock fantasma, sin alertas, caducidad manual                |
| Ventas       | 8/10     | Desconfianza en stock, proformas manuales, crédito sin control |
| Compras      | 8/10     | Pedidos por observación visual, compras duplicadas entre sedes |
| Logística    | 8/10     | Sin trazabilidad de transferencias, sin métricas             |
| Bodega       | 7/10     | Recepción sin control previo, dependencia de una persona     |
| Gerencia     | 7/10     | Decisiones con datos poco confiables, Excel para análisis    |
| Caja         | 6/10     | Descuadres, flujo de caja sin control automático             |

---

## Hoja de ruta (4 fases)

**Fase 1 — Prevención:** Motor de alertas operativas, monitoreo de facturación, stock mínimo, notificación temprana de descuadres contables.

**Fase 2 — Base Core:** Despliegue de la capa inteligente completa. Inventario en 3 estados reales, trazabilidad profunda.

**Fase 3 — Agilidad:** Herramientas comerciales: proformas con OCR, recordatorios de cartera, bloqueos de crédito.

**Fase 4 — Inteligencia:** Dashboard gerencial de rentabilidad, compras predictivas, análisis avanzado (BI).

<!-- PENDIENTE: Definir qué módulos técnicos corresponden a cada fase y en qué orden se construyen. -->

---

## Restricciones inamovibles (el agente nunca las negocia)

1. TINI es de solo lectura. No se modifica ni se reemplaza.
2. El personal no ingresa datos dos veces. Si ya lo puso en TINI, el sistema lo lee del ETL.
3. El stack tecnológico está cerrado (ver architecture.md). No se agregan dependencias sin autorización.
4. Cumplimiento LOPDP Ecuador: toda operación sobre datos sensibles se audita.
5. Cada sucursal opera su inventario independientemente. No hay inventario global unificado.
