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
| `cd public && php -d upload_max_filesize=250M -d post_max_size=260M -d max_execution_time=300 -S localhost:8000` | Servidor local (los -d permiten subir archivos de hasta 250 MB en la sección Archivo) |
| `php tests/run.php` | Pruebas de las piezas que no necesitan base de datos (también las ejecuta CI) |
| `DB_HOST=… DB_NAME=project_ci php tests/integracion.php` | Pruebas con BD real: esquema, seed, límite de intentos y calendario (CI usa MySQL de servicio; **borra y recrea la BD indicada**, nunca usar con datos reales) |
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
| **Exportación** | Nombre `prefijo-nombre-CÓDIGO-EMPRESA-sufijo.ext`: prefijo/sufijo (fecha Y-m-d, d-m-Y, numerador, texto) se eligen una vez en el Centro de Exportación (`/exportar`, guardado en localStorage) y viajan como `nom_*` en cada POST; el servidor los lee con `nomenclatura_desde_post()` y los usa `generar_nombre_archivo()`. Sin `nom_*` queda fecha al inicio. «Exportar todo» filtra con `incluir[]`; curvas, MSR y láminas de vPlan aceptan varios (`curva_ids[]`, `msr_ids[]`, `lamina_ids[]`) y entregan ZIP. PDF tabloide 11x17" landscape; columnas semanales (≤16 sem), quincenales (≤40) o mensuales, máx. 14 columnas por página. XML es MSPDI. POD renombra el XML. |
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
| `views/programa/index.php` | Vista principal: tabla WBS (columnas según el diseño del programa) + Gantt por semanas |
| `controllers/LineatiempoController.php`, `views/lineatiempo/`, `public/vendor/lineatiempo.js`, `src/scss/paginas/_lineatiempo.scss` | **Línea de tiempo activa** (`/lineatiempo`, sección Planeación): Gantt diario de **todos los meses con actividades** (banda de meses, navegación ‹ ›/Hoy, zoom con la rueda del ratón y paneo con su botón central sobre el lienzo; `?mes=` solo ancla la vista inicial, ya no hay filtro de mes), agrupado por partida (con su `color_bg`), filtros por partida/estado/restricción y casilla **«Icono de actividad»** (disco circular sobre cada barra, filas más altas al activarla; `actividades.icono`, subido en crear/editar actividad y también como columna «Icono» del programa) con **chips grandes y legibles fuera de la barra, a su derecha** (primero el estado: ✕ roja = no iniciada, reloj amarillo = en proceso, paloma verde = completada; después la ⚠ de restricción en rojo —antes iba dentro de la barra—; la leyenda superior los explica en píldoras; los discos de estado son rellenos con glifo blanco y la ⚠ al hacer hover abre burbuja flotante con retardo y botón **Resolver** — `POST /api/lineatiempo/resolver`) y **barras arrastrables/redimensionables** (número + días de duración centrados en la barra; flechas ◀ ▶ en los extremos para cambiar solo inicio o fin) que guardan fechas al instante con `POST /api/lineatiempo/fechas` (snap a día hábil, recálculo de duración en días hábiles, `Partida::recalcularFechas()`, `ActividadMaterial::sincronizarInstalacion()` y `Dependencias::propagar()`). Icono `fa-chart-gantt` (el `fa-timeline` ya era de Líneas base). Sigue el patrón de Dependencias: datos en `window.LT`, JS vendor sin Gulp. **Exportar** (menú `gr-descargar` junto al botón «Programa»): `GET /lineatiempo/exportar?…&formato=pdf|ods` con los mismos filtros de la URL → `LineatiempoPdfExporter` (una sola hoja: el papel se calcula del contenido, con reintento si desbordara) o `LineatiempoSheetExporter` (.ods editable); la vista y la exportación comparten `LineatiempoController::datos()` (admite `estados[]` y `mes`). También se exporta desde **/exportar** (Centro de Exportación, formato `lineatiempo`: casillas de estado, «solo con restricción», icono, partida y casillas de meses → un archivo por mes y, con varios marcados, en ZIP). Sección del manual: `lineatiempo` |
| `classes/ProgramaColumnas.php`, `views/programa/diseno.php` | **Programa configurable** (como el MSR): `/programa/diseno` elige nombre del programa, columnas (renombrar/ordenar) e indicador condicional; diseño en `proyectos.programa_columnas_json` (NULL = clásico). Campos nuevos de la actividad (`responsable`, `rec_inicio/fin`, `libre_*`, `icono` — imagen en `public/build/img/actividades-iconos/`, migración `database/migracion_icono_actividad.sql`, subida con `Actividad::subirIcono()` y columna «Icono» del catálogo) se escriben con `ProgramaColumnas::guardarCampo()` (SQL propio, no `Actividad::guardar`); predecesora/sucesora usan `actividad_dependencias`. Migración `database/migracion_programa_columnas.sql`. El **PDF del programa** (`views/exportar/pdf-gantt.php`) sigue el diseño (columnas, nombres, orden, sin Acciones); el XML/POD y el Gantt no. **vPlan**: las tablas de tareas pueden agregar los campos nuevos (`VplanDiseno::COLS_PROGRAMA`, selector «Agregar campo del programa» del modo diseño; vista `_hoja.php` y PDF `pdf-vplan.php` con `ProgramaColumnas::contextoVplan/valorVplan`; solo salen si el bloque los pide). En móvil `/programa` siempre muestra Nombre y la acción de editar |
| `views/ajustes/index.php` | Jornada semanal, festivos, centro de exportación |
| `views/manual/*`, `controllers/ManualController.php` | Manual de usuario (`/manual`): `_secciones.php` (base), `_admin.php` (guía del administrador) e `_ilustraciones.php` (SVG; se insertan con `[[fig:nombre]]`). Al añadir una herramienta, documentarla ahí |

## 📚 Documentación por módulo (leer solo al tocarlo)

| Archivo | Contenido |
|---|---|
| `docs/ia-programador.md` | Programador (IA) — cuando se toque classes/Programador*.php, /programador |
| `docs/ia-calidad-datos.md` | Inconsistencias, Análisis de materiales y Materiales inteligentes (IA) |
| `docs/ia-centro-herramientas.md` | Centro de IA e Herramientas de IA fases 3-10 (informes, voz, chat, normativa, seguridad, punch…) y **Revisiones** (✅⚠❌ por proyecto, estado de uso de las herramientas) |
| `docs/redactor.md` | Redactor (documentos de texto, exportación DOCX/ODT/PDF) |
| `docs/sheetflow.md` | **SheetFlow**: tablas (celdas, fórmulas, importar/exportar XLSX/CSV/PDF) reutilizables en vPlan, Editor y Redactor |
| `docs/web-extras.md` | Landing/Demo, Bandeja/soporte/visitantes/importación y Buscador de listas |
| `docs/cambios-lineas-base.md` | Órdenes de cambio (instalación/retiro, reubicación…), líneas base y comparación, cruce de materiales, resumen ejecutivo diario y modo sin conexión |
| `docs/vplan.md` | **vPlan** libre: láminas de bloques posicionables, navegador de láminas, modo diseño (capas, guías, insertar tablas), recovery dinámico, tamaño de hoja, tareas multipartida y PDF por lámina |
| `docs/estampas.md` | **Estampas inteligentes** (bloques con etiquetas que se llenan solas, post-its de las actividades del día, plantilla de Last Planner) y **cajetines dinámicos** |
| `docs/ensamblador-documentos.md` | **Ensamblador de documentos** (PDF que une Redactor + PDF del Archivo y se actualiza solo; no es el Ensamblador de IA) |
| `docs/traductor.md` | **Traductor** es↔en de texto y documentos (motor local CTranslate2, `bin/instalar_traductor.sh`) |
| `docs/ensamblador.md` | **Ensamblador** de documentos (PDF/PPTX/DOCX → partidas, materiales, submittals con IA) y **Ordenar y renumerar** el programa (sin IA) |
| `classes/PorDondeEmpezar.php`, `controllers/EmpezarController.php`, `views/templates/por-donde-empezar.php` (+ `pde-modal.php`, `src/js/empezar.js`) | Guía «Por dónde empezar»: modal tras crear el proyecto (`?inicio=guiado\|lista`, elegido en `proyectos/crear`) y sección en Ajustes. Estado de cada paso calculado con datos reales; capturas rápidas en `/api/empezar/*`. Para añadir un paso: `PorDondeEmpezar::pasos()` + formulario en el `switch` de la vista + ruta/método en el controlador |
| `classes/RespaldoProyecto.php` | Respaldo/restauración de UN proyecto (Ajustes del proyecto): tablas por FK desde `proyectos`, archivos referenciados, auto 23:30 (`bin/respaldo_programado.php --proyectos` o primera petición tras la hora), restaurar solo recupera archivos faltantes |
| `docs/directorio.md` | **Directorio** (Administración): personal, departamentos, categorías y especialidades compartidos por las herramientas (`Classes\Catalogo`) |
| `docs/salud-busqueda.md` | **Salud del proyecto** (semáforo en Inicio y ficha), **actividades en riesgo** (material sin submittal aprobado, punch grave, permiso faltante; marca en Programa/Lookahead/Last Planner), clima↔permisos, avance desde el reporte diario y **buscador global** `/buscar` (`classes/Salud.php`, `classes/Busqueda.php`) |
| `docs/notificaciones.md` | **Campana de notificaciones** (novedades de la plataforma + actividades de hoy + acciones rápidas, con el filtro de rol del cliente) y **Novedades de la plataforma** (Ajustes + `/novedades`). Cómo publicar una novedad: `classes/Novedades.php` |
| `docs/fase3.md` | **Fase 3**: Costos y presupuesto (valor ganado, CPI/SPI, curva ligada a la Curva S y a las órdenes de cambio), Recursos y cuadrillas (carga semanal), RFI (chip de revisión, actividad bloqueada) y enlaces de solo lectura (cliente temporal + calendario `.ics`) |
| `docs/telegram.md` | **Telegram** (bot único: avisos por proyecto/grupo, avance por botones, fotos como evidencia, notas; `Telegram`, `TelegramBot`) y **clima automático** en la bitácora (`ClimaAuto`, `bin/telegram.php`, `bin/clima_programado.php`) |
| `docs/fase5.md` | **Fase 5**: API REST `/api/v1` con tokens y webhooks firmados (`ApiRest`, `ApiTokens`, `Webhooks`, `/integraciones`), interfaz ES/EN (`t()`, `bin/idioma_cobertura.php`), pruebas de calidad de la IA con casos fijos (`PruebasIA`, `/calidad-ia`) e IA nueva (predicción de atrasos, redactar RFI, contradicciones) |
| `docs/personalizacion.md` | **Fase 4**: diseño de columnas de las listas (`ListaDiseno`), campos personalizados con filtro/agrupación (`CamposPersonalizados`), plantillas de proyecto completas (`PlantillaProyecto`), marca del cliente en los PDF (`Marca`, opción `'marca'` de `Pdf`) e inicio personalizable (`InicioUsuario`) |
| `docs/piezas-compartidas.md` | **Piezas compartidas** (PHP y JS) que reemplazaron copias: `json_respuesta`, `proyecto_api`, `Pdf`, `ShellTools`, `AccesoDb`, `MarcasLienzo`, `NotasApi`, `kit-edicion.js`, `notas.js`. Revisar **antes** de escribir código parecido |

> Menú: las herramientas hermanas comparten entrada y se navegan con pestañas (`pestanas_herramienta()`): Lookahead+Planificador, Archivo+Visor, Revisión de datos (Inconsistencias+Análisis de materiales), Editor+Símbolos. Las rutas antiguas siguen funcionando. «Ensamblador de documentos» se llama **Unir documentos (PDF)**.

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

10. **Burbujas de ayuda:** cualquier elemento con `data-ayuda="qué hace"` (+ `data-manual="/manual#seccion"`, `data-ayuda-titulo`) muestra a los 700 ms una burbuja con botón al manual (`src/js/ayuda.js`, estilos `.burbuja-ayuda` en `_layout.scss`). Cada herramienta nueva del menú debe agregarse a `ayudas_herramientas()` en `views/templates/herramientas-proyecto.php` (descripción + id de su sección del manual); `atributos_ayuda()` (`includes/funciones.php`) arma los atributos.

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
- **Tema por proyecto** (Ajustes → General → Apariencia del proyecto): `proyectos.tema_modo` (`usuario`/`claro`/`oscuro`) y `proyectos.tema_paleta` (24 paletas en `classes/TemaProyecto.php`, cada una con su tono claro y oscuro de la paleta ya cargada). `views/layout.php` pinta `data-tema-fijo` + `data-paleta` y un `<style>` con `--primary`, `--primary-hover` y `--primary-glow`; con `tema_modo = usuario` manda `localStorage.theme` como siempre. Se guarda en la BD (no en el navegador) y las exportaciones no cambian
- Paleta slate con acento naranja (`--primary`) y azul (`--secondary`)
- Tipografía: Inter (texto) y Fira Code (códigos/fechas)
- Medidas en `rem` (html font-size: 62.5%)
- Responsive con breakpoints `$bp-mobile: 48rem`, `$bp-tablet: 80rem`

## 🚧 Pendiente / Roadmap futuro

- [ ] Notificaciones por email cuando una restricción se marca como resuelta
- [x] Dependencias entre actividades y ruta crítica (CPM) — `classes/RutaCritica.php`, `classes/Dependencias.php`
- [x] Curva S de avance programado vs real — `classes/CurvaS.php`
- [ ] Integración con 4WLA (vista semanal lookahead)
- [x] API REST para sincronización con herramientas externas — `classes/ApiRest.php`, `classes/Webhooks.php` (`docs/fase5.md`)
- [x] Adjuntar evidencias fotográficas a actividades — `EvidenciaController`
- [x] Multi-idioma (ES/EN) — `t()` + `includes/lang/en.php`; `php bin/idioma_cobertura.php` (`docs/fase5.md`)

## 🔐 Seguridad y operación (añadido 2026-10-16)

- **Subidas de imágenes:** usa siempre `guardar_imagen_subida($campo, $carpeta)` (comprueba el tipo real y genera el nombre). Nunca uses la extensión de `$_FILES[..]['name']`.
- **IP del visitante:** `ip_cliente()` (en `includes/funciones.php`); detrás del Ingress hay que definir `TRUSTED_PROXIES` (el chart de Helm ya lo hace con `10.1.0.0/16`).
- **Fuerza bruta:** `Classes\LoginLimite` bloquea 15 min tras 5 fallos por IP + correo (tabla `login_intentos`, migración `database/migracion_login_intentos.sql`). Si la tabla no existe, el login sigue funcionando sin límite. `limpiar()` borra registros de +24 h y se llama en ~1 % de los inicios de sesión.
- **Parámetros de URL:** un valor array (`?estado[]=…`) se ignora en los sitios que lo usan como clave; `s()` también acepta arreglos sin romper. No convertir `$_GET`/`$_POST` completos a texto: hay formularios con `campo[]` legítimos.
- **Respaldos:** `RESPALDO_COPIA_DIR` (opcional) copia cada respaldo automático a otra ubicación fuera del volumen de datos.
- **Apache:** `docker/apache.conf` desactiva PHP en `uploads`, `exportaciones` y `build/img`.
- **Estudio del proyecto:** lecciones y extractos en `../clases/` (se regeneran con `clases/herramientas/generar_extractos.py`).

## 🖥 App de escritorio (solo admin)

- Código en `desktop/` (Electron, JS sin build). Instaladores en `storage/instaladores/` (volumen del clúster, fuera de git), gestionados desde `/sistema#escritorio` (`classes/Instaladores.php`). Las actualizaciones las consulta la app en `/api/escritorio/version` con un token de dispositivo (solo se guarda su hash). Las API keys viven cifradas en el equipo del admin, nunca en el servidor.
