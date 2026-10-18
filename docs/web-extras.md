# Landing/Demo, Bandeja/soporte/visitantes/importación y Buscador de listas

_Movido de `AGENTS.md`. Se lee solo al trabajar en este módulo._

## 🌐 Landing y Demo

- `/` muestra la página de presentación (`views/landing/index.php`, estilos `src/scss/landing.scss` → `public/build/css/landing.css`) si no hay sesión; con sesión, el dashboard. `/inicio` la muestra siempre.
- `/demo` entra como el usuario de solo lectura `demo@projectweb.demo` (rol visitante) al proyecto con código `DEMO`. Se crea/recrea con `php database/seed_demo.php` (descarga fotos de Unsplash; fechas relativas a hoy). Al final ejecuta `database/seed_demo_extras.php` (dependencias/ruta crítica, Archivo, Editor, Símbolos y View360 con fotos 360 de Poly Haven CC0); también puede correrse solo y solo toca el proyecto DEMO.
- Correo de contacto de los botones de planes: variable `CONTACT_EMAIL` en `.env`.
- Ojo: en `@media` el `rem` vale 16px (no 10px del `html`), así que `48rem` = 768px.

## 📥 Bandeja, soporte, visitantes e importación

- Formulario de contacto de la landing → `POST /contacto` (`ContactoController`, tabla `contactos`; trampas anti-spam y 5 mensajes/hora por IP). El admin los ve en `/bandeja` junto con los mensajes de soporte (tabla `soportes`); el menú muestra el número de sin leer.
- Rol cliente: `/soporte` (CRUD de sus propios mensajes) y `/usuarios` como «Mis Visitantes» (máx. `Usuario::MAX_VISITANTES_CLIENTE` = 2, siempre rol visitante, `creado_por` = el cliente; solo asigna sus propios proyectos). Nunca se toma rol/límites del formulario.
- `/programa/importar`: importa XML MSPDI o .pod en XML (`classes/ProgramaImportador.php`, sin IA). Solo trae nombre, fechas, avance, hitos, prioridad, notas y niveles; predecesoras/recursos/calendarios no.
- Capturas del recorrido de la landing: `public/build/img/landing/tour-*.jpg` (tomadas del demo: 8 pestañas del recorrido; hay que regenerarlas si cambia mucho la UI).

## 🔎 Buscador de listas

`src/js/app.js` agrega una caja de búsqueda (sin acentos/mayúsculas, varias palabras = todas) a toda `table.tabla` con ≥5 filas. `data-sin-buscador` la excluye, `data-buscable` la fuerza con ≥2 filas, y un input con `data-filtra="selector"` filtra esa tabla en vivo (ej. materiales). En el programa, la partida se queda si coincide ella o alguna actividad.

