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
