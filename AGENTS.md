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

## 📚 Documentación por módulo (leer solo al tocarlo)

| Archivo | Contenido |
|---|---|
| `docs/ia-programador.md` | Programador (IA) — cuando se toque classes/Programador*.php, /programador |
| `docs/ia-calidad-datos.md` | Inconsistencias, Análisis de materiales y Materiales inteligentes (IA) |
| `docs/ia-centro-herramientas.md` | Centro de IA e Herramientas de IA fases 3-10 (informes, voz, chat, normativa, seguridad, punch…) y **Revisiones** (✅⚠❌ por proyecto, estado de uso de las herramientas) |
| `docs/redactor.md` | Redactor (documentos de texto, exportación DOCX/ODT/PDF) |
| `docs/web-extras.md` | Landing/Demo, Bandeja/soporte/visitantes/importación y Buscador de listas |
| `docs/cambios-lineas-base.md` | Órdenes de cambio (instalación/retiro, reubicación…), líneas base y comparación, cruce de materiales, resumen ejecutivo diario y modo sin conexión |

## ⚠️ Common Gotchas

1. **Recalcular fechas de partida:** Después de crear/editar/eliminar actividad, llamar `Partida::recalcularFechas()` para actualizar `fecha_inicio`/`fecha_fin` de la partida.
2. **Status automático:** `Actividad::sincronizarStatus()` se llama antes de guardar: 0% = no_iniciada, 100% = completada, resto = en_proceso.
3. **Paginación en PDF:** `PdfExporter::generar()` elige la granularidad según la duración del proyecto y pagina con `$maxColumnasPorPagina = 14`.
4. **Hash de sesión admin por defecto:** El seed crea `admin@po.local / admin123`. **Cambiar en producción**.
5. **Exportaciones** se generan en `public/exportaciones/` y se borran justo después de la descarga (sin historial). El servidor web necesita permisos de escritura ahí.
6. **MSPDI XML:** Es compatible con MS Project pero NO es `.mpp` binario. El usuario abre el XML en MSP y guarda como .mpp desde allí.
7. **PHP:** `«$var»` en comillas dobles lee `»` como parte del nombre: usa `{$var}`. `array_merge(...$arregloConClavesTexto)` falla: usa `array_values()`.
8. **Pruebas con copia de BD:** `DB_NAME` del entorno NO se respeta; crea `project_claude_<n>` (`mysqldump --no-data`), fija `$_ENV['DB_NAME']` en un `auto_prepend_file` y borra solo esa BD. No uses `project_test`.
9. **Herramientas de IA nuevas:** llamar a `Auditoria::registrar()` al aplicar/revertir/rechazar y agregarlas al catálogo `desktop/src/herramientas.js` (detalle en `docs/ia-centro-herramientas.md`). Nada cambia datos sin que el admin acepte.

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
- `phpoffice/phpword` ^1.4 — exportación DOCX/ODT del Redactor

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

## 🖥 App de escritorio (solo admin)

- Código en `desktop/` (Electron, JS sin build). Instaladores en `storage/instaladores/` (volumen del clúster, fuera de git), gestionados desde `/sistema#escritorio` (`classes/Instaladores.php`). Las actualizaciones las consulta la app en `/api/escritorio/version` con un token de dispositivo (solo se guarda su hash). Las API keys viven cifradas en el equipo del admin, nunca en el servidor.
