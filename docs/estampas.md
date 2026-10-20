# Estampas inteligentes y cajetines dinámicos (2026-10-19)

_Se lee solo al trabajar en este módulo._ Migración: `database/migracion_estampas.sql` (también en `schema.sql`; **aplicar en el clúster**).

## Qué es
Una **estampa** es un bloque reutilizable cuyas etiquetas (prefijo + campo + sufijo) se llenan solas con datos del proyecto. Se guarda en `estampas` (`proyecto_id, nombre, config_json`), se administra en **Símbolos › Estampas inteligentes** y se coloca en el vPlan como bloque `estampa` (`config: estampa_id, fecha_modo hoy|fija, fecha, actividad_id`). Se dibuja en la hoja y en el PDF (`Estampas::renderGuardada(..., 'cq'|'pt')`) y se actualiza en cada visita.

## Piezas
- `classes/Estampas.php`: catálogo `CAMPOS` (clave => grupo, etiqueta, tipo texto|fecha|num|pct, ejemplo; los `act_*` necesitan una actividad), **piezas puras** (`formatear`, `semaforo`, `reemplazar` — con pruebas en `tests/run.php`), `limpiarConfig` (sanea: campos del catálogo, colores, rangos, partidas/actividades del proyecto), `render` (HTML con tablas y estilos en línea para que Dompdf y el navegador lo dibujen igual; unidades `cq` = cqw de la hoja, `pt` × K en el PDF, `px` en la vista previa) y `plantillas()` (los seis ejemplos, entre ellos **Last Planner · Post-its del día**).
- `classes/EstampaContexto.php`: datos bajo demanda con caché por petición (proyecto, lámina, avance/SPI desde `CurvaS`, ruta crítica desde `Dependencias::datos`, restricciones, órdenes de cambio, retiros desde `Cambios`, último reporte, responsables de `actividad_responsables`). Opciones: `corte`, `actividad`, `autor`, `escala`, `revision`, `lamina_n/total`.
- `models/Estampa.php` (`deProyecto`, `paraInsertar`), `controllers/EstampaController.php` (rutas `POST /simbolos/estampa[/eliminar|/duplicar|/plantilla]`, `POST /api/estampas/vista` y `/api/estampas/textos`; editar = gestor), editor visual en `public/vendor/estampas-editor.js` (datos en `window.EST_DATA`), estilos en `src/scss/paginas/_simbolos.scss`.
- Bloque `estampa` en `VplanBloque::TIPOS`, `VplanController::configBase`, `views/vplan/_hoja.php`, `views/exportar/pdf-vplan.php` y el modo diseño (`vplan-diseno.js`: `cargarEstampa`, lista en Insertar, campos en Propiedades).

## Etiquetas
`t` = `campo` | `texto` | `semaforo` | `actividades`. Campo: `campo, prefijo, sufijo, formato (fecha: '', corto, largo, completo, mes, iso · números: 0-3 decimales), actividad_id (0 = la del bloque)` + estilo (`tamano, negrita, color, alinear`). Semáforo: `modo mayor|menor, verde, rojo` (entre los umbrales = amarillo). Actividades del día: `fecha_modo corte|fija, partidas[], responsable, max, columnas, campos[], solo_pendientes, color_por`. «Hoy» es la fecha de corte del contexto (la de hoy o la fija del bloque); si no es día hábil (`Calendario`) se usa el siguiente.
- Disposición de la estampa: `lineas` (una debajo de otra), `tabla` (prefijo a la izquierda, dato a la derecha) o `banda`. Título opcional con campos.
- Variables en textos y cajetines: `{campo}` o `{campo:formato}` con las claves de `Estampas::CAMPOS`. Las antiguas (`{proyecto} {titulo} {fecha} {autor} {lamina} {total} {escala}`) siguen igual.

## Cajetines dinámicos
`models/Cajetin.php` conserva `filas_json` (hasta 20 renglones «Etiqueta | valor»). El formulario ahora es un editor de renglones (`.cj-form` en `estampas-editor.js`: campo insertable, plantillas, vista previa con `/api/estampas/textos`) que rellena el mismo `textarea[name=filas]`. `Vplan::cajetinDeBloque` resuelve con `Estampas::texto`. En el Editor (Konva) `Cajetin::paraEditor` resuelve en el servidor las variables nuevas y deja las antiguas sin formato para el navegador.

## Notas
- Dompdf no respeta `height:100%` de tablas anidadas: los post-its tienen alto mínimo (`base × 5.6`) y no se estiran al bloque; ajusta el tamaño del bloque o la letra de la etiqueta.
- Sin la tabla `estampas` (migración pendiente) `Estampa::disponible()` es falso: el bloque muestra «Estampa no disponible» y la pestaña lista vacío.
- Demo: `database/seed_demo_estampas.php` (también lo ejecuta `seed_demo_nuevas.php`) crea las seis estampas, un cajetín dinámico y la lámina «LAST PLANNER DEL DÍA».

## Lienzo: editor gráfico de estampas (2026-10-19)
`config.modo = 'lienzo'` (las estampas antiguas siguen en `etiquetas`). Los **elementos** (`config.elementos`, máx. 80) se colocan en % del bloque (`x, y, w, h`) y el bloque tiene tamaño libre (`w`, `h` en % de la lámina; 1 unidad de hoja = 1 pt en una lámina de 432 mm; `Estampas::dimensiones($cfg)` da [ancho, alto] en unidades).
- Gráficos (`linea, rect, elipse, rombo`): color, grosor, trazo, relleno liso o degradado (`v|h|d`), borde, esquinas y sombra. Se dibujan en **un solo SVG** (`<img>` data URI, lo leen igual el navegador y Dompdf) que va **siempre debajo** de los elementos inteligentes.
- Inteligentes: `texto` (prefijo y valor fijos, admite `{campos}`), `campo`, `semaforo`, `partida_dia` (partida(s) con actividades en la fecha; `varias: todas|unir`; o una fija), `actividades_dia` (renglones con rombos verde/amarillo/rojo y días para terminar, encabezado opcional), `logo` (`fuente: compania|proyecto|contratista`, con `background-size: contain`).
- Piezas: `Estampas::limpiarElementos`, `lienzoSvg`, `actividadesDia`, `partidaDia`, `cajaTexto`, `renderLienzo`; `EstampaContexto::logo/partidaNombre` y las opciones `partida`, `max`, `offset` (el Last Planner las usa para decir «esta tarjeta»). Editor: `pintarLienzo` en `estampas-editor.js` (arrastrar, redimensionar con la esquina, dibujar con la herramienta elegida, panel de propiedades, vista previa real con `/api/estampas/vista`).
- Plantillas nuevas: **Last Planner · Post-it 7 x 7 cm** (`postItLastPlanner()`) y **Encabezado de MSR** (`encabezadoMsr()`, 802 x 90 pt).

## Plantilla del post-it de Last Planner
En `/lastplanner` el gestor elige «Plantilla del post-it»: *Clásica* o una estampa de lienzo (`Estampa::usarEnLastPlanner`, marca `config.lp`; solo una por proyecto, sin migración). La plantilla **solo se edita en el editor de estampas**. Pantalla: `.lp-tarjeta-est`; PDF: `LastPlannerPdfExporter` toma el tamaño de la estampa y acomoda las tarjetas que quepan en carta (`pdf-lastplanner.php`).

## Encabezado del MSR dinámico
`msrs.encabezado_id` (migración `database/migracion_msr_encabezado.sql`; sin ella el MSR sigue con el encabezado de siempre). El formulario del MSR (`views/msr/formulario.php`) elige una estampa de lienzo; `pdf-msr.php` la dibuja a todo el ancho (`K = 802 / ancho`). Pendiente: usarlo también en la hoja de cálculo/pantalla del MSR.
