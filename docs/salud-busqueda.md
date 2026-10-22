# Salud del proyecto, actividades en riesgo y buscador global

_Se lee solo al tocar estos módulos._

## Salud (`classes/Salud.php`)
- **Semáforo** (`Salud::semaforo($proyecto)`): indicadores atraso, restricciones, submittals (envío vencido en `msr_items` + `submittals.estatus = 'rechazado'`), punch grave, órdenes de cambio `solicitada`, permisos (`Seguridad::resumen`) y actividades en riesgo. Nivel `ok|aviso|critico`; el global es el peor. No guarda nada. Se pinta con `views/salud/_semaforo.php` (`$saludCompacto = true` en el Inicio, completo en `/proyectos/ver`). Estilos en `src/scss/componentes/_salud.scss`.
- **Riesgo por actividad** (`Salud::riesgosActividades($pid)`, con caché por petición): actividades pendientes (no hito, <100 %) que inician dentro de `HORIZONTE_DIAS` (30) con (a) material vinculado (`actividad_materiales`) sin submittal aprobado (`aprobado`/`aprobado_an`) ni `msr_items.status` A/AN — las actividades con `sin_material = 1` no cuentan —, (b) pendiente de punch `alta`/`critica` abierto o en proceso, (c) permiso que falta (`Seguridad::requeridos`). `Salud::insignia()` pinta la marca «En riesgo»; se usa en `views/programa/index.php` (+ filtro `riesgo=1` en `ProgramaController`), `views/lookahead/index.php` y, como ▲ naranja solo en pantalla, en `views/lastplanner/tarjeta.php` (`LastPlanner` agrega `riesgo` a cada renglón; el PDF no cambia).
- **Clima ↔ seguridad** (`Salud::climaYPermisos`, `permisosDelDia`): permisos de `PERMISOS_CLIMA` vigentes en días con `Clima::condicion()`. Se muestra en `/seguridad`, cuenta en el semáforo y se menciona en la descripción de la propuesta «día no hábil por clima» (`Herramientas/ClimaCalendario`).
- Para añadir otro motivo de riesgo: sumarlo en `riesgosActividades()` y, si es un indicador nuevo, en `semaforo()`.

## Reporte diario → avance
`POST /reportes/avance` (`ReporteController::avance`, requiere `proyecto` en el cuerpo): cambia `actividades.progreso` solo donde el usuario lo modificó, usa `Actividad::guardar()` y mueve la anotación de `actividad_avance` de hoy a la **fecha del reporte** (restaurando la de hoy si ya existía). Las fotos del reporte ya salen en Evidencias ligadas a las actividades del reporte.

## Buscador global (`classes/Busqueda.php`, `/buscar`)
Una consulta por fuente (`fuentes()`): actividades, materiales, submittals, minutas, bitácora, reportes, archivo, punch, cambios, permisos, Redactor y normativa. Cada palabra (máx. 6, ≥ 2 letras) debe aparecer (`CONCAT_WS … LIKE`, sin acentos por la colación). Cada fuente declara su ruta base y se omite si `herramienta_permitida()` la oculta al visitante. Caja en `views/templates/topbar.php` (solo con proyecto abierto y fuera del demo). Para sumar una fuente: añadir una entrada en `fuentes()` con `{P}` (proyecto) y `{W:col1,col2}` (palabras).
