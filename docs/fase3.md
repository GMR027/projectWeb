# Fase 3: costos, recursos, RFI y enlaces de solo lectura (2026-10-21)

_Se lee solo al tocar estos módulos._ Migración: `database/migracion_fase3.sql` (también en `schema.sql`; **aplicar en el clúster**, ya está en `aplicar_migraciones_cluster.sh`). Añade 7 tablas y el valor `rfi` a `revisiones.tipo`/`revisiones_historial.tipo`.

| Módulo | Ruta | Código | Quién |
|---|---|---|---|
| Costos y presupuesto | `/costos` | `classes/Costos.php`, `CostosController`, `views/costos/` | Solo quien gestiona el proyecto (admin / cliente dueño). Clave de visitante `costos` |
| Recursos y cuadrillas | `/recursos` | `classes/Recursos.php`, `RecursosController`, `views/recursos/` | Ve todos; edita quien gestiona. Clave `recursos` |
| RFI | `/rfi` | `classes/Rfi.php`, `RfiController`, `views/rfi/` | Ve todos; edita quien gestiona. Clave `rfi` |
| Compartir (enlaces) | `/compartir` | `classes/Enlaces.php`, `CompartirController`, `views/compartir/` | Solo quien gestiona |
| Página del cliente / `.ics` | `/cliente?t=`, `/calendario/ics?t=` | `EnlaceClienteController`, `views/cliente/`, `classes/CalendarioIcs.php`, `views/layout-publico.php` | Públicas (sin sesión), por token |

## 3.1 Costos
- `costos_partidas(partida_id, presupuesto)` y `costos_movimientos(tipo real|estimacion, fecha, concepto, monto, partida_id?, orden_cambio_id?, estado borrador|aprobada|pagada)`. El estado solo importa en estimaciones (las `borrador` no cuentan).
- **Presupuesto vigente (BAC)** = presupuesto de la partida + `impacto_costo` de las órdenes de cambio **autorizadas** (`OrdenCambio::AUTORIZADAS`) cuya actividad cae en la partida; las que no tienen actividad suman al total del proyecto. Es la conexión con las órdenes de cambio.
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

## 3.4 / 3.5 Enlaces
- `enlaces_proyecto(tipo cliente|ics, token 40 hex, secciones, expira, revocado, accesos, ultimo_acceso)`. `Enlaces::resolver()` valida token, tipo, caducidad y revocación y cuenta el acceso. El token viaja en la URL (`t=`); no hay lista de enlaces pública.
- **Cliente:** `layout-publico.php` (`'publicoLayout' => true` en `Router::render`; `noindex`, `no-store`). Secciones `avance` (Curva S, partidas, próximas 3 semanas e hitos), `fotos` (evidencias + fotos de reportes) y `reportes` (sin nombres de personal). Nunca muestra costos, personal ni documentos.
- **.ics:** `CalendarioIcs::generar()`: actividades pendientes de la semana pasada a las próximas 4, todos los hitos y la fecha límite de RFI abiertos; eventos de día completo con UID estable (`act-ID`, `hito-ID`, `rfi-ID`) y `REFRESH-INTERVAL` de 6 h. La ruta no lleva extensión (`/calendario/ics`) porque `php -S` devuelve 404 a URLs con extensión que no existen.
- `enlaces_proyecto` está en `RespaldoProyecto::EXCLUIDAS`: los enlaces no se respaldan ni se restauran.

## Pruebas
Con copia de BD (AGENTS.md, punto 8). Se probó: captura de presupuesto/costos/estimaciones, órdenes de cambio, sobrecarga con dos actividades al 70 %, RFI vencido, token inválido/caducado (404) y `.ics` (líneas ≤ 75 octetos).
