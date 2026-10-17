# AGENTS.md — ProjectWeb Programa de Obra

## Project Overview
PHP 8 MVC project para gestión de programas de obra con Gantt, calendario operativo y exportación multi-formato. Basado en patrón UpTask_MVC con paleta de diseño Vanguard CivilTech (dark mode, glassmorphism).

## 🛠 Essential Commands

| Command | Description |
|---|---|
| `composer install` | Instalar dependencias PHP (Dompdf, PHPMailer, phpdotenv) |
| `npm install` | Instalar dependencias Node |
| `npx gulp` | Compilar SCSS + JS |
| `npx gulp dev` | Watch mode para desarrollo |
| `cd public && php -d upload_max_filesize=100M -d post_max_size=110M -d max_execution_time=300 -S localhost:8000` | Servidor local (los -d permiten subir archivos de hasta 100 MB en la sección Archivo) |
| `mysql -u root < database/schema.sql` | Crear/resetear BD |

## 🏗 Architecture

- **Entry point:** `public/index.php` — registra rutas y delega a Router
- **Router:** `Router.php` — mapea URL a `[Controller::class, 'method']`, renderiza vistas con `extract()`
- **Models:** `models/*.php` extienden `ActiveRecord.php` (CRUD genérico, sanitización, alertas)
- **Views:** `views/**/*.php` con layouts `layout.php` (auth) y `layout-auth.php` (login)
- **Clases auxiliares:** `classes/` (Calendario, GanttRenderer, Exportadores/*)

## 📋 Key Conventions

| Topic | Guidance |
|---|---|
| **Roles** | 3 roles: `admin` (full), `cliente` (ver+descargar), `visitante` (solo ver). Helpers: `is_admin()`, `puede_editar()`, `puede_descargar()` |
| **Calendario** | Cada proyecto tiene jornada semanal (factor 0-1 por día) + festivos. `Calendario::calcularFechaFin($inicio, $diasHabiles)` respeta esta config. |
| **Gantt** | Renderizado con CSS Grid. `GanttRenderer::getFilas()` retorna array con posiciones de columna para cada partida/actividad. |
| **Exportación** | Nomenclatura auto `Y_m_d_NombreUsuario.ext`. PDF tabloide 11x17" landscape; columnas semanales (≤16 sem), quincenales (≤40) o mensuales, máx. 14 columnas por página. XML es MSPDI. POD renombra el XML. |
| **BD** | Nombre: `project`. ActiveRecord usa string interpolation (sanitiza con `$db->escape_string`). |
| **Assets** | Compilar con Gulp. SCSS en `src/scss/`, JS en `src/js/`, salida a `public/build/`. |

## 🧩 Useful File Map

| Path | Purpose |
|---|---|
| `public/index.php` | Front controller, registra todas las rutas |
| `Router.php` | Despachador de rutas + renderer de vistas |
| `models/ActiveRecord.php` | Base CRUD para todos los modelos |
| `models/Proyecto.php` | Proyecto + `inicializarJornadaDefault()` |
| `models/Partida.php` | Contenedor de actividades, tiene `color_bg` |
| `models/Actividad.php` | Actividad con `sincronizarStatus()` según progreso |
| `classes/Calendario.php` | Motor de días hábiles (lee jornada + festivos) |
| `classes/GanttRenderer.php` | Prepara estructura para el Gantt (semanas + posiciones) |
| `classes/Exportadores/*` | PdfExporter (Dompdf), MspdiXmlExporter, PodExporter |
| `views/programa/index.php` | Vista principal: tabla WBS + Gantt por semanas |
| `views/ajustes/index.php` | Jornada semanal, festivos, centro de exportación |

## ⚠️ Common Gotchas

1. **Recalcular fechas de partida:** Después de crear/editar/eliminar actividad, llamar `Partida::recalcularFechas()` para actualizar `fecha_inicio`/`fecha_fin` de la partida.
2. **Status automático:** `Actividad::sincronizarStatus()` se llama antes de guardar: 0% = no_iniciada, 100% = completada, resto = en_proceso.
3. **Paginación en PDF:** `PdfExporter::generar()` elige la granularidad según la duración del proyecto y pagina con `$maxColumnasPorPagina = 14`.
4. **Hash de sesión admin por defecto:** El seed crea `admin@po.local / admin123`. **Cambiar en producción**.
5. **Exportaciones** se generan en `public/exportaciones/` y se borran justo después de la descarga (sin historial). El servidor web necesita permisos de escritura ahí.
6. **MSPDI XML:** Es compatible con MS Project pero NO es `.mpp` binario. El usuario abre el XML en MSP y guarda como .mpp desde allí.

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

## 🔍 Need to Verify?

- Login: `http://localhost:8000/login` con `admin@po.local / admin123`
- Crear proyecto → se inicializa jornada por defecto (L-V=1, S=0.5, D=0)
- Crear partida → crear actividad → verificar que `fecha_fin` se calcula con calendario
- Agregar festivo → crear actividad que cruce esa fecha → verificar que se salta
- Exportar PDF → abrir archivo y verificar tabloide + paginación
- Exportar XML → abrir en MS Project y verificar estructura WBS

## 📦 Dependencies

**Composer:**
- `vlucas/phpdotenv` — variables de entorno
- `phpmailer/phpmailer` — emails (futuro: confirmación de cuenta, notificaciones)
- `dompdf/dompdf` ^3.0 — generación de PDF

**NPM (dev):**
- `gulp`, `gulp-sass`, `sass` — build pipeline
- `gulp-sourcemaps`, `gulp-plumber`, `gulp-concat`, `gulp-terser`, `gulp-rename`

## 🎨 Design System

Implementación de **Vanguard CivilTech** (`design/vanguard_civiltech/DESIGN.md`):
- Tema claro/oscuro con variables CSS (`src/scss/base/_variables.scss`, atributo `data-theme`; por defecto oscuro)
- Paleta slate con acento naranja (`--primary`) y azul (`--secondary`)
- Tipografía: Inter (texto) y Fira Code (códigos/fechas)
- Medidas en `rem` (html font-size: 62.5%)
- Responsive con breakpoints `$bp-mobile: 48rem`, `$bp-tablet: 80rem`

## 🚧 Pendiente / Roadmap futuro

- [ ] Notificaciones por email cuando una restricción se marca como resuelta
- [x] Dependencias entre actividades y ruta crítica (CPM) — `classes/RutaCritica.php`, `classes/Dependencias.php`
- [x] Curva S de avance programado vs real — `classes/CurvaS.php`
- [ ] Integración con 4WLA (vista semanal lookahead)
- [ ] API REST para sincronización con herramientas externas
- [x] Adjuntar evidencias fotográficas a actividades — `EvidenciaController`
- [ ] Multi-idioma (ES/EN)

## 🔐 Seguridad y operación (añadido 2026-10-16)

- **Subidas de imágenes:** usa siempre `guardar_imagen_subida($campo, $carpeta)` (comprueba el tipo real y genera el nombre). Nunca uses la extensión de `$_FILES[..]['name']`.
- **IP del visitante:** `ip_cliente()` (en `includes/funciones.php`); detrás del Ingress hay que definir `TRUSTED_PROXIES` (el chart de Helm ya lo hace con `10.1.0.0/16`).
- **Respaldos:** `RESPALDO_COPIA_DIR` (opcional) copia cada respaldo automático a otra ubicación fuera del volumen de datos.
- **Apache:** `docker/apache.conf` desactiva PHP en `uploads`, `exportaciones` y `build/img`.
- **Estudio del proyecto:** lecciones y extractos en `../clases/` (se regeneran con `clases/herramientas/generar_extractos.py`).
