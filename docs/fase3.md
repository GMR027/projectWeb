# Fase 3: costos, recursos, RFI y enlaces de solo lectura (2026-10-21)

_Se lee solo al tocar estos módulos._ Migración: `database/migracion_fase3.sql` (también en `schema.sql`; **aplicar en el clúster**, ya está en `aplicar_migraciones_cluster.sh`). Añade 7 tablas y el valor `rfi` a `revisiones.tipo`/`revisiones_historial.tipo`. Para cantidades con precio unitario: `database/migracion_cantidades_precio.sql`.

| Módulo | Ruta | Código | Quién |
|---|---|---|---|
| Costos y presupuesto | `/costos` | `classes/Costos.php`, `CostosController`, `views/costos/` | Solo quien gestiona el proyecto (admin / cliente propietario o asignado). Clave de visitante `costos` |
| Almacén | `/almacen` | `classes/Almacen.php`, `AlmacenController`, `views/almacen/` | Ve todos; edita quien gestiona o el colaborador con `almacen`. Clave `almacen` |
| Recursos y cuadrillas | `/recursos` | `classes/Recursos.php`, `RecursosController`, `views/recursos/` | Ve todos; edita quien gestiona. Clave `recursos` |
| RFI | `/rfi` | `classes/Rfi.php`, `RfiController`, `views/rfi/` | Ve todos; edita quien gestiona. Clave `rfi` |
| Compartir (enlaces) | `/compartir` | `classes/Enlaces.php`, `CompartirController`, `views/compartir/` | Solo quien gestiona |
| Página del cliente / `.ics` | `/cliente?t=`, `/calendario/ics?t=` | `EnlaceClienteController`, `views/cliente/`, `classes/CalendarioIcs.php`, `views/layout-publico.php` | Públicas (sin sesión), por token |

## 3.1 Costos
- `costos_partidas(partida_id, presupuesto, presupuesto_manual, origen manual|cantidades)` y `costos_movimientos(tipo real|estimacion, fecha, concepto, monto, partida_id?, orden_cambio_id?, estado borrador|aprobada|pagada)`. El estado solo importa en estimaciones (las `borrador` no cuentan).
- **Cantidades con precio unitario** (`Classes\Cantidades`): `actividad_cantidades` guarda clave, concepto, cantidad, unidad, `precio_unitario` e `importe` (= cantidad × precio). Una fila por actividad. Se captura en **Editar actividad → Concepto de obra** (`POST /programa/actividades/cantidad`).
- **Presupuesto automático:** `Cantidades::resumenPartidas()` dice, por partida, cuántas actividades con duración están valuadas (cantidad > 0 y precio no nulo). Si están **todas**, el presupuesto es la suma de importes (`origen = cantidades`); si falta alguna, se conserva `costos_partidas.presupuesto` manual y `Costos::resumen()` expone `faltantes` y `suma_parcial` para avisar. `guardarPresupuesto()` guarda siempre el valor manual y no pisa una partida calculada. `recalcularProyecto()` (botón «Recalcular desde cantidades») sincroniza datos anteriores.
- **Dinero comprometido** (`Classes\Compromisos`): `compromisos` (tipo orden_compra|subcontrato|servicio, estado borrador|vigente|parcial|cerrado|cancelado, proveedor, número, partida, actividad opcional, monto, fechas, archivo y `motivo_sobregiro`) y `compromiso_aplicaciones` (factura aplicada: `movimiento_id`, monto, fecha). Solo `vigente` y `parcial` reservan. `Compromisos::resumenProyecto()` da el saldo comprometido por partida (monto − facturado); `Costos::resumen()` calcula `disponible = BAC − AC − comprometido`. Un sobregiro se permite con motivo firmado (≥10 caracteres). `Compromisos::aplicar()` crea el costo real y descuenta el saldo; borrar esa factura (`Costos::eliminarMovimiento` → `quitarAplicacionesDeMovimiento`) reabre el compromiso.
- **Estimaciones completas** (`Classes\Estimaciones`): `costos_contrato` (monto, anticipo, retención %, IVA %), `estimaciones` (subtotal, IVA, retención, amortización, ajuste, total bruto, neto, estado y `movimiento_id`) y `estimacion_partidas` (detalle por partida). `calcular()` aplica IVA, retención y amortización sobre `subtotal + ajuste`; `resumenContrato()` da ejecutado, saldo, fondo retenido y anticipo pendiente. Al aprobar/pagar se crea o actualiza el movimiento de Costos con el **total bruto** (la curva y el KPI lo usan); `Costos::resumen()` añade `estimado_neto` con el neto de las formales y el monto de las simples antiguas. El PDF (`/estimaciones/pdf`) sale con la marca del cliente.
- **Presupuesto vigente (BAC)** = presupuesto de la partida (manual o calculado) + `impacto_costo` de las órdenes de cambio **autorizadas** (`OrdenCambio::AUTORIZADAS`) cuya actividad cae en la partida; las que no tienen actividad suman al total del proyecto. Es la conexión con las órdenes de cambio.
- **Valor ganado** (`Costos::resumen`): PV = BAC × avance programado a hoy, EV = BAC × avance real; ambos pesan cada actividad por su duración (hitos pesan 0), igual que `CurvaS`. CPI = EV/AC, SPI = EV/PV, EAC = BAC/CPI. Los costos y estimaciones sin partida cuentan en los totales.
- **Conexión con la Curva S:** `Costos::curva()` toma `CurvaS::calcular()` y multiplica plan % y real % por el BAC; AC y estimado salen acumulados de los movimientos. Se dibuja en la pestaña Resumen (Chart.js) y `curvas/s` tiene un botón a Costos.
- Salud: indicador «Índice de costo (CPI)» (solo para quien gestiona y si hay presupuesto y costo real): < 1 aviso, < 0.9 crítico.

## 3.2 Recursos
- `recursos(tipo persona|cuadrilla, personal_id?, integrantes, horas_dia)` y `actividad_recursos(actividad_id, recurso_id, dedicacion %)`.
- Carga semanal = Σ dedicación × días hábiles de la actividad dentro de la semana ÷ días hábiles de la semana (`Calendario`). Las actividades al 100 % no cargan. > 100 % = sobrecarga (rojo), ≥ 85 % alto (amarillo). Las horas (para cuadrillas) = dedicación × días × horas_dia × integrantes.
- Salud: indicador «Recursos sobrecargados» (próximas 4 semanas, `Recursos::sobrecargasProximas`).

## 3.3 RFI
- `rfis` (número RFI-001…, asunto, pregunta, respuesta, responsable, prioridad, estado abierto/respondido/cerrado, fecha, fecha_limite, fecha_respuesta, `actividad_id` = la que bloquea).
- Un RFI **abierto** sobre una actividad sin terminar la marca «en riesgo» (`Salud::riesgosActividades`, tipo `rfi`; sale en Programa, Lookahead y Last Planner) y vence al pasar la fecha límite. Indicador «RFI abiertos» en Salud.
- **Chip de revisión:** `Revisiones` tiene el tipo `rfi` (`huellaRfi`, `elementos`, `nombreDe`, `huellaDe`); la lista de RFI pinta el chip (solo admin) y Revisiones tiene la pestaña «RFI».
- **Láminas del Editor:** `rfi_laminas` adjunta láminas de `/graficos` (misma consulta `Grafico::laminasParaSeleccion`, migración `database/migracion_rfi_laminas.sql`). El botón **PDF** de la lista (`/rfi/pdf`) y la tarjeta de RFI del Centro de Exportación (`formato=rfi`, varios en ZIP) usan `RfiPdfExporter` y ponen las láminas debajo de la pregunta y la respuesta.

## 3.4 / 3.5 Enlaces
- `enlaces_proyecto(tipo cliente|ics, token 40 hex, secciones, expira, revocado, accesos, ultimo_acceso)`. `Enlaces::resolver()` valida token, tipo, caducidad y revocación y cuenta el acceso. El token viaja en la URL (`t=`); no hay lista de enlaces pública.
- **Cliente:** `layout-publico.php` (`'publicoLayout' => true` en `Router::render`; `noindex`, `no-store`). Secciones `avance` (Curva S, partidas, próximas 3 semanas e hitos), `fotos` (evidencias + fotos de reportes) y `reportes` (sin nombres de personal). Nunca muestra costos, personal ni documentos.
- **.ics:** `CalendarioIcs::generar()`: actividades pendientes de la semana pasada a las próximas 4, todos los hitos y la fecha límite de RFI abiertos; eventos de día completo con UID estable (`act-ID`, `hito-ID`, `rfi-ID`) y `REFRESH-INTERVAL` de 6 h. La ruta no lleva extensión (`/calendario/ics`) porque `php -S` devuelve 404 a URLs con extensión que no existen.
- `enlaces_proyecto` está en `RespaldoProyecto::EXCLUIDAS`: los enlaces no se respaldan ni se restauran.

## 3.6 Almacén
- `almacenes` (uno principal por proyecto, se crea solo), `compra_lineas` (material, cantidad, precio, importe y `recibido`), `recepciones`, `salidas` y `almacen_movimientos` (entrada, salida, ajuste_entrada, ajuste_salida). `materiales.stock_minimo` dispara el aviso de bajo mínimo.
- **La orden de compra es un compromiso** (`compromisos.tipo = orden_compra`) con líneas de material. `Almacen::guardarOrden()` llama a `Compromisos::guardar()` con el total y guarda las líneas; el dinero se reserva igual que en el paso 5.
- **Recepción** (`Almacen::recibir()`): cantidades parciales o totales, valida contra `compra_lineas.recibido` y crea movimientos de entrada. Si se escribe factura, aplica el total al compromiso (`Compromisos::aplicar()`) dentro de la misma transacción: el costo real se reconoce al recibir. Sin factura, solo entra al almacén.
- **Salida** (`Almacen::salida()`): vale por actividad; valida existencia por material y usa el costo promedio de las entradas (o el `costo_unitario` del material) para valorizar el consumo. No crea costo real: es consumo interno.
- **Ajuste** (`Almacen::ajuste()`): entrada o salida manual con motivo obligatorio; una salida no puede dejar existencia negativa.
- **Existencia** = Σ entradas + ajustes de entrada − salidas − ajustes de salida. Se calcula en `Almacen::stock()`; no se guarda a mano.
- Permisos: la clave `almacen` está en `Model\Usuario::HERRAMIENTAS` y en el menú; el visitante solo consulta. Migración `database/migracion_almacen.sql`.

## Pruebas
Con copia de BD (AGENTS.md, punto 8). Se probó: captura de presupuesto/costos/estimaciones, órdenes de cambio, sobrecarga con dos actividades al 70 %, RFI vencido, token inválido/caducado (404) y `.ics` (líneas ≤ 75 octetos).
