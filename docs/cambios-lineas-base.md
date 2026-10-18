# Órdenes de cambio, líneas base, cruce de materiales, resumen diario y modo sin conexión

_Se lee solo al trabajar en este módulo._ Migración: `database/migracion_cambios.sql` (también en `schema.sql`).

## Órdenes de cambio (`/cambios`)
- Tablas `ordenes_cambio` (estado: borrador → solicitada → aprobada/rechazada → ejecutada → cerrada; impacto en costo y días; `actividad_id` opcional) y `cambio_partidas` (renglones). Modelos `OrdenCambio` y `CambioPartida`; controlador `CambioController`; lógica de consulta en `classes/Cambios.php`.
- Tipos de renglón: `instalacion_temporal` (siempre `requiere_retiro`), `instalacion`, `reubicacion`, `demolicion`, `adicion`, `modificacion`. Ciclo: `pendiente → ejecutado → retirado` (si requiere retiro) o `→ verificado` (si no) y `cancelado`. `CambioPartida::estadosDe()` decide qué estados vale cada renglón y `cambiarEstado()` completa `fecha_ejecucion` / `fecha_retiro_real`.
- **Retiro pendiente** = renglón con `requiere_retiro=1` y estado `ejecutado` (`Cambios::retirosPendientes()`): se muestra en la pantalla, en el resumen diario y se marca «vencido» o «sin programar».
- Una orden **justifica** una desviación del programa si está en `solicitada/aprobada/ejecutada/cerrada` y apunta a la actividad (`ordenes_cambio.actividad_id` o `cambio_partidas.actividad_id`): `Cambios::justificantes()`.
- Permisos: ver = quien ve el proyecto; editar = `puede_gestionar_proyecto()`. Herramienta de visitante `cambios` (`Usuario::HERRAMIENTAS`: `/cambios`, `/api/cambios`, `/lineas-base`).

## Líneas base (`/lineas-base`)
- `lineas_base` + `lineas_base_actividades` + `lineas_base_materiales`: foto inmutable (sin llaves foráneas a actividades/materiales para que sobreviva si se borran) con la lista de actividades en ruta crítica (`criticas_json`). `LineasBase::capturar()` / `comparar()`.
- Comparación por actividad: `igual | movida | nueva | eliminada`, deltas en **días hábiles** (`RutaCritica::difHab`), causa `oc | material | sin_justificar`, entradas/salidas de la ruta crítica y Gantt con barra «fantasma» de la línea base. La ruta crítica solo se compara si las dependencias de obra no están apagadas.
- La línea base de referencia del resumen diario es la más reciente (o la del parámetro `linea`).

## Materiales contra cronograma (`/cambios/cruce`)
- `classes/CruceMateriales.php`: cruza la necesidad (inicio de la actividad o `fecha_prevista` del renglón con material) con compra, entrega y submittal. Estados: `compra_vencida`, `sin_fecha`, `tarde`, `submittal`, `a_tiempo`, `en_sitio`. `evaluar()` es puro y tiene pruebas en `tests/run.php`.

## Resumen ejecutivo diario (IA)
- Herramienta `resumen_diario` (`classes/Herramientas/ResumenEjecutivoDiario.php`, registrada en `Registro`): usa el marco de informes (`/informes-ia`, PDF, borrador/aprobado). **Toda cifra la calcula el servidor**; la IA (desde la app de escritorio) solo redacta el análisis, las sugerencias y los pendientes. Sin IA se genera igual con texto de plantilla (botón «Resumen ejecutivo de hoy» en `/cambios`, solo admin).
- Semáforo `CRITICO | ATENCION | EN ORDEN` (`ResumenEjecutivoDiario::semaforo`). Nada cambia datos del proyecto: es solo lectura, por eso no registra en `Auditoria`.

## Modo sin conexión (por equipo)
- Se activa en **Ajustes → Modo sin conexión** (`localStorage po_offline`). Piezas: `public/sw.js` (Service Worker: estáticos con caché primero, páginas con red primero y copia local si no hay red; nunca guarda `/api/`, `/login`, `/logout`, `/uploads`, `/exportaciones`) y `src/js/offline.js` (`window.POOffline`: cola en IndexedDB, subida automática al volver la red, aviso en pantalla, «Descargar este proyecto»).
- Hoy se encola el **cambio de estado de renglones** (`POST /api/cambios/partida-estado`). Manda `base` (el `actualizado` que el usuario vio): si el renglón cambió en el servidor responde 409 y el usuario elige «Aplicar el mío» (`forzar=1`) o «Descartar». Para encolar otra operación: `POOffline.encolar({url, cuerpo, etiqueta})` y que el endpoint soporte `base`/409.
- Al cerrar sesión (`/logout`) y al desactivar el modo se borran las cachés `po-*`. Las fotos y subidas de archivos requieren conexión. Funciona igual dentro de la app de escritorio (la vista web es un origen http(s) normal).
- Prueba hecha: Electron oculto + servidor detenido (página cacheada, cola, subida, conflicto y resolución).
