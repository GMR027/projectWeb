# Navegación compacta — barra de 96 px y riel opcional

Rediseño del navbar de ProjectWeb. Sustituye la barra de hasta 63 iconos (que a 1366/1440 px
ocupaba 3–4 filas y unos 386–465 px, y hasta ~600 px a 1024 px) por una **barra de aplicación de
56 px + una barra de contexto de 40 px** en el modo compacto, o por un **riel lateral de 64 px**
en el modo opcional. La propuesta visual se generó en Google Stitch (proyecto
**ProjectWeb · Navegación compacta**, `projects/14751474689918404117`) y se implementó con las
variables del tema, sin pegar el HTML de Stitch.

## Objetivo

- Máximo **96 px** de navegación superior en escritorio (56 px al hacer scroll) y **56 px** en móvil.
- Todas las herramientas accesibles en **≤ 2 clics**; las frecuentes, en 1.
- Sin duplicar menús: el menú lateral queda para lo global y la navegación del proyecto vive en la
  barra/riel/panel.
- Sin columnas nuevas y sin dependencias nuevas. Una migración de una línea
  (`database/migracion_navegacion_compacta.sql`) enciende los nombres por defecto.

## Antes / después

| Pantalla | Antes (aprox.) | Después |
|---|---:|---:|
| 1024×768 | 600 px (5 filas + topbar envuelto) | 56 px arriba + 64 px de barra inferior |
| 1280×800 | 600 px | 96 px |
| 1366×768 | 465 px | 96 px |
| 1440×900 | 445 px | 96 px |
| 1920×1080 | 386 px | 96 px |

Cálculo sobre las medidas del CSS anterior (`breadcrumb-item` 4.6 rem = 51.5 px, 8 grupos, 63
iconos, html al 70 %). La barra de contexto no es fija: al leer quedan solo los 56 px de la barra
de aplicación.

La app considera «móvil» hasta **1024 px** (`$bp-movil: 64rem` en las media queries, que usan el
tamaño inicial de 16 px): hasta ahí se usa la barra inferior de 64 px y la hoja «Más». Entre 1025 y
1280 px la barra compacta aparece con el dock deslizable; desde 1440 px caben las 10 herramientas
de Planeación con nombres.

## Modos

`proyectos.menu_superior` conserva sus valores para no migrar:

| Valor | Nombre en Ajustes | Qué hace |
|---|---|---|
| `clasico` | **Barra compacta (recomendado)** | 56 px de aplicación + 40 px de sección y herramientas. Es el modo predeterminado. |
| `panel` | **Riel lateral** | Riel de 64 px con las 7 secciones; el panel de la sección se abre sobre el contenido. La barra superior queda en 56 px. |
| `pestanas` (antiguo) | — | Se muestra como barra compacta. `TemaProyecto::normMenu()` lo normaliza a `clasico`. |

El ajuste `proyectos.nombres_herramientas` sigue vigente: con **Sí** el dock escribe la etiqueta
corta junto al icono; con **No** quedan solo iconos (el nombre y la explicación aparecen al pasar
el puntero). El panel, el riel y la hoja móvil muestran siempre los nombres.

## Piezas

### Barra de aplicación (`views/templates/appbar.php`)

- Logo y selector de proyecto (estado, código, fechas, duración y accesos a Salud/Ajustes/Ver
  todos). El periodo que antes vivía en la topbar se movió a este popover.
- Buscador del proyecto (en móvil se abre una caja superpuesta) y botón de cuadrícula
  «Todas las herramientas», siempre visible aunque la barra de contexto se haya ido con el scroll.
- Ayuda, campana (novedades + hoy + alertas de portafolio), tema y avatar.
- El avatar concentra nombre, rol, correo, proyectos, sesiones, manual, ajustes de app (admin),
  soporte (cliente), idioma y salir. Se eliminaron los botones sueltos de casa, engranaje, escudo,
  idioma y salida.
- El `h1` de la página pasa a ser `sr-only`; el título visual vive en el hero o en el `.page-header`
  del contenido, sin duplicarse.

### Barra de contexto (`views/templates/nav-contexto.php`)

- Selector de sección con las 7 categorías de `herramientas_proyecto()` y su conteo.
- Dock de la sección activa con chips de 26–28 px y etiqueta corta (`corto`).
- Desbordamiento horizontal con degradado y botón `⋯`; el botón de cuadrícula abre el panel con
  todas las herramientas y la chincheta fija la herramienta activa.
- En páginas globales (Inicio, Proyectos, Portafolio, Usuarios, Organizaciones, Bandeja, Sistema)
  la misma barra pinta la navegación global.
- `nav_activa()` resuelve la herramienta activa por la ruta más específica, incluidos los alias
  (`tambien`: `/planificador`, `/simbolos`, `/analisis-materiales`, etc.).

### Riel lateral (`views/templates/nav-riel.php`)

- 64 px, 7 iconos de sección, separador, cuadrícula, manual y menú general.
- El panel de la sección se abre a la derecha del riel (28 rem) y se cierra con Escape, clic fuera
  o su botón. La sección seleccionada se marca mientras el panel está abierto y se restaura la de
  la ruta al cerrar.
- En móvil el riel se oculta y mandan la barra inferior y la hoja «Más».

### Panel «Todas las herramientas» (`views/templates/nav-panel.php`)

- `<dialog>` nativo: foco atrapado, Escape, backdrop y animación.
- Buscador, chips por sección, fijadas (`localStorage.pw-nav-fijadas`) y recientes
  (`pw-recientes`, que ya usaba el menú lateral).
- Cada herramienta se puede fijar; las fijadas se muestran arriba y se pueden quitar.
- Se abre con la cuadrícula, el `⋯`, la barra móvil o `Ctrl/⌘ + K`. En móvil es una hoja inferior
  con etiquetas cortas; en escritorio, un panel de hasta 84 rem.
- `window.PW_NAV` lleva `key`, etiqueta, etiqueta corta, URL, icono, grupo y texto de búsqueda.

### Navegación móvil (`views/templates/nav-inferior.php`)

- Barra fija de 64 px + `safe-area-inset-bottom`: **Inicio · Programa · Reporte diario · Más**.
- «Más» abre el panel de herramientas (o el menú general si no hay proyecto).
- La hamburguesa flotante se eliminó; el menú general se abre desde la barra de aplicación.
- El divisor flotante se aparta por encima de la barra inferior.

## Archivos

| Archivo | Cambio |
|---|---|
| `views/layout.php` | Resuelve proyecto/permisos una vez, calcula grupos y modo; pinta appbar + app-cuerpo (riel + principal) + panel + barra móvil. |
| `views/templates/appbar.php` | Nuevo: barra de aplicación, popover del proyecto, notificaciones y menú de la cuenta. |
| `views/templates/nav-contexto.php` | Nuevo: `nav_activa()`, `navegacion_proyecto()` y `navegacion_global()`. |
| `views/templates/nav-riel.php` | Nuevo: riel y panel de sección. |
| `views/templates/nav-panel.php` | Nuevo: panel «Todas las herramientas» y `window.PW_NAV`. |
| `views/templates/nav-inferior.php` | Nuevo: barra inferior móvil. |
| `views/templates/navegacion-global.php` | Nuevo: `menu_global_nav()` (fuente única del menú lateral y la barra global). |
| `views/templates/sidebar.php` | Menú lateral solo global + recientes + acciones rápidas; sin duplicar las herramientas del proyecto. |
| `views/templates/herramientas-proyecto.php` | Agrega `corto` a cada herramienta para el dock. |
| `src/scss/layout/_navegacion.scss` | Todo el lenguaje visual nuevo; se importa en `app.scss` y `app-base.scss`. |
| `src/js/menu-proyecto.js` | Sección/dock, panel, fijadas, recientes, riel, menús de la barra, búsqueda móvil y `Ctrl/⌘+K`. |
| `src/js/app.js` | Tema con varios botones (`data-tema-toggle`) y recientes con el nuevo selector. |
| `classes/TemaProyecto.php` | `MENUS`/`MENUS_AYUDA` nuevos; `pestanas` se normaliza a `clasico`. |
| `views/ajustes/index.php` | Textos de los modos y de «Mostrar nombres». |
| `views/manual/_secciones.php` | Manual de la barra, el panel, el riel y las sesiones. |
| `tests/run.php` | Pruebas de layout, modos, `nav_activa()`, etiquetas cortas y panel. |

No hay columnas nuevas: se reutilizan `menu_superior` y `nombres_herramientas`. La migración
`database/migracion_navegacion_compacta.sql` (idempotente) pone el valor por defecto de
`nombres_herramientas` en 1 y enciende los nombres en los proyectos existentes; cada proyecto
puede volver a «No» en Ajustes → Apariencia.

## Pantallas de Stitch

Proyecto: `https://stitch.withgoogle.com/projects/14751474689918404117`

- **ProjectWeb - Navegación 96px (Ultra Compacto 1440px)** — recomendada.
- **ProjectWeb - Riel Lateral 64px y Flyout 280px** — alternativa.
- **ProjectWeb Móvil - Programa de Obra** — barra de 56 px + barra inferior.
- **ProjectWeb Móvil - Hoja de Herramientas ‘Más’** — hoja inferior.
- **ProjectWeb - Rediseño Navegación 96px** — primera iteración (las pestañas de texto no caben).

El sistema de diseño es **Vanguard CivilTech** (`assets/3de8209815a649b191a831f31188b11f`).
La implementación usa las variables del proyecto (`--primary`, `--primary-glow`, `--primary-ink`,
`--bg-secondary`, `--surface`, etc.), no los colores fijos del HTML de Stitch.

## Cómo probar

```bash
npx gulp
cd public && php -S localhost:8000 router.php
php tests/run.php
php bin/idioma_cobertura.php
```

- Altura medida: `document.querySelector('.appbar').offsetHeight + document.querySelector('.nav-contexto')?.offsetHeight` = **96 px**.
- Ancho: `document.documentElement.scrollWidth === innerWidth` en 1440 y 390.
- 1366/1440/1920: el dock de Planeación muestra sus 10 herramientas; en Administración aparece
  `⋯` y el panel filtrado.
- 1280: el dock se desliza sin romper la página; 1024: barra inferior y hoja «Más».
- 390×844: appbar 56, barra inferior 64, contexto oculto; «Más» abre la hoja; sin scroll horizontal.
- Modos `clasico`, `panel` y el antiguo `pestanas`; tema claro/oscuro; paletas; ayuda encendida y
  apagada; admin, cliente, visitante y colaborador.
- Teclado: `Ctrl/⌘+K` abre el panel; Tab recorre; Escape cierra el panel, los menús y el buscador
  móvil; `aria-expanded`/`aria-current` correctos.
- Impresión: `@media print` oculta appbar, contexto, riel, panel y barra inferior.

## Móvil: sin desbordes horizontales

En móvil el selector de proyecto de la barra **debe poder encogerse**. `.appbar-izq` y
`.appbar-proyecto-wrap` usan `flex: 1 1 auto; min-width: 0`, y `.appbar-proyecto` ocupa el
100 % del espacio disponible con el nombre truncado. No vuelvas a dar `flex: none` a
`.appbar-izq`: a 360 px la barra mide 391 px y empuja el documento, recortando `/reportes`,
`/programa` y el resto de páginas con proyecto. Las tablas anchas siguen desplazándose dentro
de `.tabla-wrapper`; eso es scroll interno intencional, no desborde de página.

Para comprobarlo con Playwright a 320/360/390 px:

```js
document.documentElement.scrollWidth === innerWidth
```

El ajuste principal vive en `src/scss/layout/_navegacion.scss` (bloque `@media (max-width: $bp-movil)`).
