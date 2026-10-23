# Landing/Demo, Bandeja/soporte/visitantes/importación y Buscador de listas

_Movido de `AGENTS.md`. Se lee solo al trabajar en este módulo._

## 🌐 Landing y Demo

- `/` muestra la página de presentación (`views/landing/index.php`, estilos `src/scss/landing.scss` → `public/build/css/landing.css`) si no hay sesión; con sesión, el dashboard. `/inicio` la muestra siempre.
- `/demo` entra como el usuario de solo lectura `demo@projectweb.demo` (rol visitante) al proyecto con código `DEMO`. Se crea/recrea con `php database/seed_demo.php` (descarga fotos de Unsplash; fechas relativas a hoy; asigna **un icono por disciplina** a las actividades, SVG en `public/build/img/actividades-iconos/demo-*.svg`, para verlos en la Línea de tiempo con la casilla «Icono de actividad» y en el programa con la columna «Icono»). Al final ejecuta `database/seed_demo_extras.php` (dependencias/ruta crítica, Archivo, Editor, Símbolos y View360 con fotos 360 de Poly Haven CC0); también puede correrse solo y solo toca el proyecto DEMO.
- Correo de contacto de los botones de planes: variable `CONTACT_EMAIL` en `.env`.
- Ojo: en `@media` el `rem` vale 16px (no 10px del `html`), así que `48rem` = 768px.

## 📥 Bandeja, soporte, visitantes e importación

- Formulario de contacto de la landing → `POST /contacto` (`ContactoController`, tabla `contactos`; trampas anti-spam y 5 mensajes/hora por IP). El admin los ve en `/bandeja` junto con los mensajes de soporte (tabla `soportes`); el menú muestra el número de sin leer.
- Rol cliente: `/soporte` (CRUD de sus propios mensajes) y `/usuarios` como «Mis Visitantes» (máx. `Usuario::MAX_VISITANTES_CLIENTE` = 2, siempre rol visitante, `creado_por` = el cliente; solo asigna sus propios proyectos). Nunca se toma rol/límites del formulario.
- `/programa/importar`: importa XML MSPDI o .pod en XML (`classes/ProgramaImportador.php`, sin IA). Solo trae nombre, fechas, avance, hitos, prioridad, notas y niveles; predecesoras/recursos/calendarios no.
- Capturas del recorrido de la landing: `public/build/img/landing/tour-*.jpg` (tomadas del demo: 8 pestañas del recorrido; hay que regenerarlas si cambia mucho la UI).

## 🔎 Buscador de listas

`src/js/app.js` agrega una caja de búsqueda (sin acentos/mayúsculas, varias palabras = todas) a toda `table.tabla` con ≥5 filas. `data-sin-buscador` la excluye, `data-buscable` la fuerza con ≥2 filas, y un input con `data-filtra="selector"` filtra esa tabla en vivo (ej. materiales). En el programa, la partida se queda si coincide ella o alguna actividad.


## 🗂 Carpetas por herramienta (Redactor, SheetFlow, Minutas)

- `classes/Carpetas.php` + `CarpetaController` (`POST /carpetas/renombrar`, `/carpetas/mover`) + `views/templates/carpetas-ui.php`. La carpeta es el campo `departamento` (`''` = «Sin departamento»). Minutas lo gana con `database/migracion_minutas_departamento.sql` (también en `schema.sql`; **aplicar en el clúster**). Redactor y SheetFlow ya lo tenían.

## 🧾 MSR configurable

- Cada MSR puede tener columnas propias: `msrs.columnas_json` (NULL = diseño clásico; `Msr::normalizarColumnas`), columnas de texto libre `txt_N` guardadas en `msr_items.extras_json` y color de título por partida propia en `msr_partidas.color`. En la tabla se arrastra el encabezado para reordenar (`POST` orden) y con doble clic se cambia título, color y grupo (`MsrController::orden|columna`). Migración: `database/migracion_msr_columnas.sql` (también en `schema.sql`; **aplicar en el clúster**). El PDF/Excel (`MsrSheetExporter`, `pdf-msr.php`) y el importador (`MsrImportador`) respetan esas columnas.

## 🖥 Sesión persistente en la app de escritorio

- Si el User-Agent trae `ProjectWebDesktop` (lo pone `desktop/src/main.js`, también en `session.fetch`), `includes/app.php` usa cookie de 1 año y guarda las sesiones en `storage/sesiones-app/` (ignorada por git) para que el GC de 24 min no las borre.

## 🌐 Servicios y demo ampliado (2026-10-19)

- `/servicios` (`LandingController::servicios`, `views/landing/servicios.php`): servicios administrativos de seguimiento de obra, **sin nombrar herramientas ni IA** (texto indicativo sobre un «grupo especializado»). La barra superior es `views/landing/_nav.php` (compartida; `$base = '/'` fuera de la landing). La landing suma «Todo en uno» (`#todo-en-uno`), bloques de Cambios/Documentación, un teaser de Servicios y pasos de 4. **Nunca mostrar herramientas exclusivas del admin** (IA, Centro de IA, Revisiones, Ensamblador, Ajustes, Sistema).
- Las pestañas del recorrido solo aparecen si existe `public/build/img/landing/tour-<id>.jpg` (se filtran en `views/landing/index.php`).
- `database/seed_demo_nuevas.php` (lo ejecuta `seed_demo.php` al final; `--limpiar` borra solo lo suyo) puebla el DEMO con órdenes de cambio, líneas base, materiales vs cronograma, Datos de obra (crea 4 tablas del Ensamblador si faltan: **aplicar su migración en el clúster**), Redactor, SheetFlow, minutas en carpetas, MSR configurable, vPlan libre, símbolos/cajetines, planos Rev. A/B para el Comparador y Normativa. Créditos de imágenes: `public/build/img/landing/CREDITOS.txt`.
- Las capturas `tour-*.jpg` se tomaron del demo con Electron oculto (viewport 1440×900, sin barra lateral/migas) y se recortaron 30 px arriba; hay que regenerarlas si cambia mucho la UI.

## Divisor de ventana
- Botón flotante abajo a la derecha (`public/vendor/divisor.js`, estilos `src/scss/componentes/_divisor.scss`, cargado desde `views/layout.php`). Funciona igual en la web y en la app de escritorio (que muestra esa misma web; el botón se desplaza para no tapar el globo del chat por el User-Agent `ProjectWebDesktop`).
- Cubre la pantalla con un lienzo de 1 a 6 paneles (iframes de la misma app). Cada panel: selector de herramienta (se llena con los enlaces del menú lateral), zoom 30–200 % (escala del iframe), dividir a la derecha/abajo y cerrar. Plantillas de 1, 2, 3 y 4 ventanas en la barra superior; los separadores se arrastran. Esc o «Cerrar divisor» vuelve a la vista normal.
- Dentro de un iframe el layout añade la clase `html.embebido` (sin sidebar). Los iframes nunca se mueven en el DOM (se reposicionan por porcentajes) para que no se recarguen. El diseño se guarda en `localStorage` (`divisor:v1`).
