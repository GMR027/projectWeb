# Propuesta de rediseño — Programa de obra

Rediseño de la página `/programa` y de sus ventanas modales. La propuesta visual se generó en
Google Stitch (proyecto **ProjectWeb · Programa de Obra V2**, `projects/3585367008641246780`)
y se implementó con las variables del tema de la aplicación, sin pegar el HTML de Stitch.

## Objetivo

Convertir la página en un **centro de control de obra**: que el avance, los riesgos, las
restricciones y los días restantes se lean de un vistazo, que la tabla WBS sea cómoda con
muchas actividades y que el Gantt y los formularios no obliguen a salir de la página.

## Decisiones de diseño

- **Hero compacto** con proyecto, chips de contexto, avance ponderado, fechas y CTA principal.
- **KPIs accionables**: actividades, en riesgo, restricciones activas, días hábiles restantes
  y bloqueos de punch/calidad. Cada tarjeta lleva al filtro o a la herramienta correspondiente.
- **Barra de mando fija**: búsqueda por código/nombre/responsable, chips de estatus, filtros
  avanzados con chips removibles y selector **Tabla · Gantt · Ambos**.
- **Tabla WBS jerárquica**: partidas colapsables, filas compactas, edición en línea de fechas y
  avance, acciones agrupadas, fila rápida «Agregar actividad» desplegable y columna de acciones
  fija a la derecha.
- **Cronograma semanal** con banda de meses, semana actual resaltada, etiquetas fijas, línea de
  «Hoy», barras clicables, dependencias y scroll propio. Ya no se oculta en móvil.
- **Móvil**: hero y KPIs en dos columnas, tabla compacta tipo tarjeta, acciones táctiles y
  modales de fechas en hoja inferior.
- **Modales**: alta/edición de actividad, partida y subactividad dentro de `/programa` con
  iframe y cierre por `postMessage`; modal de fechas con duración en días hábiles y atajos;
  renombrar columna y confirmaciones con el lenguaje visual del resto de la app.
- **Accesibilidad**: foco visible, `aria-label` en acciones de icono, `prefers-reduced-motion`,
  contraste con las variables del tema y cierre con `Escape`.
- **Sin cambios de esquema**: los KPIs se calculan con las filas que ya prepara el Gantt.

## Archivos principales

| Archivo | Cambio |
|---|---|
| `classes/ProgramaResumen.php` | Nuevo: conteos, meses del Gantt y días hábiles restantes (funciones puras) |
| `classes/ProgramaFilas.php` | Búsqueda libre por código, nombre y responsable |
| `controllers/ProgramaController.php` | Resumen, `q`, `vista`, duración en días hábiles y guardado modal |
| `views/programa/index.php` + `_hero`, `_barra`, `_tabla`, `_gantt`, `_modales`, `_scripts` | Vista dividida en parciales |
| `views/programa/crear-actividad.php`, `editar-actividad.php`, `crear-partida.php`, `editar-partida.php`, `subactividad.php` | Formularios con secciones, pie fijo y modo modal |
| `views/layout-modal.php`, `views/layout.php` | Cierre de modales desde el iframe |
| `public/vendor/programa.js` | Búsqueda, vistas, colapso, modales, Gantt y avisos |
| `src/scss/paginas/_programa.scss` | Lenguaje visual completo del programa |
| `src/scss/componentes/_divisor.scss` | Formularios a pantalla completa dentro del modal |
| `tests/run.php` | Pruebas de búsqueda, resumen y meses |

## Cómo probar

```bash
npx gulp
cd public && php -S localhost:8000 router.php
php tests/run.php
php bin/idioma_cobertura.php
```

Abrir un proyecto con partidas y actividades, probar los filtros, la búsqueda, el cambio de
vista, contraer partidas, editar fechas/avance en línea, abrir una actividad en modal, el modal
de fechas en móvil y el tema claro/oscuro.

## Fase 2: suite de planeación

El mismo lenguaje visual se extendió a **Dependencias**, **Línea de tiempo activa**, **Línea de
balance (takt)** y **vPlan**, incluyendo sus ventanas, paneles y formularios.

- **Capa compartida** `src/scss/componentes/_suite.scss` (importada en `app-base.scss` y
  `app.scss`): hero compacto, KPIs, barra de herramientas, paneles, leyendas, estados vacíos,
  formularios y pestañas. Se activa con `body[data-vista^="dependencias|lineatiempo|balance|vplan"]`,
  porque las vistas agregan su sufijo (`dependencias-index`, `vplan-ver`…).
- **Dependencias** (`src/scss/paginas/_dependencias.scss`): modos como tarjetas compactas,
  KPIs, barra fija, diagrama e inspector con estado vacío, y modal de reprogramación.
- **Línea de tiempo** (`src/scss/paginas/_lineatiempo.scss`): KPIs, filtros compactos, leyenda
  en píldoras y etiquetas de dos líneas (código + duración / nombre) sin cambiar el motor de
  arrastre ni las llamadas a `/api/lineatiempo/fechas`.
- **Balance** (bloque `.balance-page` al final de `src/scss/paginas/_fase3.scss`): configuración
  en tarjetas, diagrama con leyenda por oficio, generador y tabla de cruces con desfase. La
  rejilla se adapta sola cuando el visitante solo ve una tarjeta.
- **vPlan** (bloque al final de `src/scss/paginas/_vplan.scss`): hub con guía/resumen, tarjetas
  de vPlan, galería de láminas en cuadrícula plana (el grupo ya se lee en cada tarjeta), espacio
  de trabajo con modos Ver · Acomodar · Dibujar, panel de diseño, materiales y formularios.
- **Sin cambios de rutas, permisos, selectores ni esquema.** La galería de vPlan sigue siendo
  la vista inicial y el reordenamiento por arrastre guarda el orden con la API existente.

Para revisar: `npx gulp`, abrir cada herramienta con un proyecto con datos y alternar tema
claro/oscuro, móvil, los modos de vPlan y los modales de Dependencias/notas/materiales.

## Fase 3: herramientas de campo y seguimiento

La misma capa visual se aplicó a **Calendario**, **Líneas base**, **Tipo de obra** (`/plantillas`),
**Reporte diario**, **Bitácora**, **Minutas**, **Evidencias**, **Seguridad**, **Punch list**,
**Calidad**, **RFI** y **Mapa de avance**.

- **Capa compartida** `src/scss/componentes/_suite.scss`: se amplió la lista de `body[data-vista^=…]`
  y se sumaron pestañas de herramienta (`.centro-ia-tabs`), chips de filtro (`.cb-chip`) y secciones
  plegables (`.suite-details`). El hero, los KPIs, paneles, leyendas, estados vacíos y formularios
  siguen saliendo de la misma hoja.
- **Hoja de página** `src/scss/paginas/_campo.scss` (se carga con `css_paginas_de_vista()` en las
  vistas mapeadas): rejilla del calendario, tarjetas de líneas base, ranking de tipo de obra,
  línea de tiempo de evidencias, tarjetas de permisos y punch, y detalles de mapa de avance.
- **Calendario**: KPIs de días hábiles, actividades y festivos; barra de mes con navegación y
  «Crear actividad»; panel del mes con leyenda de estados; tabla del mes y estado vacío.
- **Líneas base**: KPIs de fotos, actividades y fin programado; formulario de captura en panel;
  lista de líneas base y comparación con KPIs de desfase y Gantt base vs actual.
- **Tipo de obra**: identificación automática con barras de coincidencia, catálogo de tipos,
  secuencia de etapas, submittals, materiales críticos y base de partidas.
- **Reportes, bitácora, minutas y evidencias**: hero con contexto, KPIs, filtros en barra,
  paneles de lista y línea de tiempo de fotos con tarjetas legibles.
- **Seguridad, punch y calidad**: KPIs de riesgo con semáforo, pestañas, formularios plegables,
  tarjetas de permisos/pendientes y paneles de detenidas, liberaciones, ensayos y expedientes.
- **RFI y mapa de avance**: KPIs de vencidos y bloqueos, filtros por estado, paneles de solicitudes,
  capas por lámina y comparación planos vs programa.

**Sin cambios de rutas, permisos, selectores ni esquema.** Las vistas conservan sus formularios,
IDs, APIs y comportamiento; el cambio es de marcado y estilos. Para revisar: `npx gulp`, abrir cada
herramienta con un proyecto con datos y probar escritorio/móvil y tema claro/oscuro.


## Fase 4: Materiales y compras · Documentos y diseño

La misma capa visual se aplicó a **Materiales y compras** (MSR, Submittals, Catálogo de materiales,
Almacén, Dependencias de materiales) y a **Documentos y diseño** (Archivo, Visor, Editor, Redactor,
SheetFlow, View360, Ensamblador de documentos, Comparador, Anotador, Traductor, Normativa y sus
pantallas de detalle).

- **Capa compartida** `src/scss/componentes/_suite.scss`: se amplió la lista de `body[data-vista^=…]`
  a las 16 herramientas, se añadieron buscadores fluidos (`.suite-busca`) y estilos comunes para
  **ventanas modales con formulario** (`form.modal-card`) y diálogos nativos (`dialog.rd-dialogo`).
- **Hoja de página** `src/scss/paginas/_documentos.scss` (se carga con `css_paginas_de_vista()`):
  barra y resumen del MSR, tablas de Submittals y materiales, badges del Almacén, archivero y visor,
  grupos de documentos, galería del Editor, View360, listas del Ensamblador, paneles del Traductor
  y de Normativa, y detalles del Comparador/Anotador.
- **MSR**: hero con conteo de renglones, en revisión, aprobados y alertas; barra de filtros y
  acciones; tabla del reporte intacta (se sigue imprimiendo igual); lista de MSR con KPIs y panel.
- **Submittals**: KPIs por estatus, panel de paquetes con cambio de estatus en línea, ficha del
  paquete con materiales y láminas, y formulario en dos secciones.
- **Catálogo de materiales**: KPIs de compra y entrega, barra de búsqueda y filtros, tabla legible
  y formulario con secciones de datos, logística y normativa.
- **Almacén**: KPIs de existencias, mínimo, órdenes y valor; pestañas compartidas; formularios de
  salida, ajuste, recepción y orden dentro de paneles.
- **Dependencias de materiales**: hero con relaciones y bloqueos; el tablero, la lista, el panel y
  los modales conservan su comportamiento y ganan el lenguaje visual común.
- **Archivo y Visor**: KPIs de archivos, carpetas, categorías y pendientes; archivero con paneles
  y modales de carpeta alineados a la izquierda, y visor con barra de filtros a ancho completo.
- **Editor, Redactor y SheetFlow**: galería con KPIs y chips, listas por departamento como paneles,
  asistentes de creación y visores de documento/tabla con cabecera consistente.
- **View360 y Ensamblador**: KPIs de recorridos, documentos y ensambles; paneles de lista y
  formularios; el Ensamblador mejora el ancho de la columna de documentos y el ajuste de acciones.
- **Comparador y Anotador**: hero con conteo de comparaciones/anotaciones; paneles de fuentes,
  guardados y documentos; las herramientas de dibujo y los diálogos mantienen su comportamiento.
- **Traductor y Normativa**: paneles de traducción, KPIs de documentos/cláusulas/vínculos,
  carga de archivos, búsqueda unificada, resultados y tablas dentro de paneles.

**Sin cambios de rutas, permisos, selectores, APIs ni esquema.** Se conservan las interacciones
(estatus de submittals, filtros del MSR, pestañas del Almacén, modales de carpetas, guardado del
Comparador y Anotador). Para revisar: `npx gulp`, abrir cada herramienta con un proyecto con datos
y probar escritorio/móvil y tema claro/oscuro.
