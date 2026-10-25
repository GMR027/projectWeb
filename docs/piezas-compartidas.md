# Piezas compartidas (reestructuración 2026-10)

Antes de agregar código nuevo, revisa si ya existe aquí. Cada pieza reemplazó copias que vivían en varios controladores, clases o editores.

## PHP

| Pieza | Para qué | Reemplazó |
|---|---|---|
| `json_respuesta($datos, $codigo)` (`includes/funciones.php`) | Responde JSON y termina | 29 `json()` privados en controladores |
| `proyecto_api('admin'\|'gestionar'\|'ver')` | Proyecto de una API o 403 en JSON | `proyectoApi()` de 10 controladores (ya no queda ninguno privado) |
| `Classes\Pdf::generar/preparar/guardar` | Único lugar donde se arma Dompdf (fuente por defecto DejaVu Sans; el resto de opciones las pasa quien llama) | 18 `new Dompdf` |
| `Classes\ShellTools` | `disponible()`, `ejecutar()` (con tiempo y tamaño máximos), `correr()`, `unirPdf()`, `libreOffice()`, `pdfATexto()` | 5 revisiones de «¿está instalado?» y 6 `proc_open` |
| `Classes\AccesoDb` (trait) | `db()`, `filas()`, `esc()`, `corto()` para clases estáticas | copias privadas en 28 clases |
| `Archivo::guardarArchivoSubido/carpetaCreada/nombreGuardado/reemplazarContenido` | Guardar un archivo en la carpeta del proyecto | 3 flujos de guardado en el Archivo |
| `Classes\MarcasLienzo` | Validar marcas y PNG que llegan del editor y subir imágenes (vPlan «Editar marcas» y Editor) | `guardarMarcas`/`subirImagen` duplicados |
| `Classes\NotasApi` | Crear, editar y borrar notas de una lámina (vPlan) o de un gráfico (View360) | `VplanNotaController` y `View360Controller::nota*` |
| `enviar_descarga($datos, $mime, $nombre, $inline)` (`includes/funciones.php`) | Cabeceras de descarga de un archivo que ya está en memoria | bloques repetidos en Redactor, SheetFlow, Seguridad, Informes IA, Punch, Centro de IA y Programador |
| `Classes\HallazgosIA` + `Controllers\HallazgosIAController` | Corridas, listado, conteos y aceptar → aplicar / rechazar / reabrir → revertir (con Auditoria) de las herramientas con hallazgos. Cada hija pone tablas y textos en constantes (`TABLA_*`, `HERRAMIENTA`, `TXT_*`) y sus hooks (`esperadoDe`, `paramsValidos`, `ejecutarAccion`, `restaurarAccion`, `enriquecer`) | `Inconsistencias` y `AnalisisMateriales` (clases y controladores) |
| `TablaDeDocumento::deHoja()` | Lector de hojas de cálculo (csv, xls, xlsx, ods) | `Sheetflow::importar` tenía su copia |
| `Classes\Catalogo` + `/directorio` | Departamentos, categorías (Archivo, Punch, Evidencias) y su renombrado en todas las herramientas; ver `docs/directorio.md` | listas de departamento copiadas en Redactor, SheetFlow, Bitácora, Minutas y Personal |
| `pestanas_herramienta($grupo, $activa, $proyecto)` (`views/templates/herramientas-proyecto.php`) | Pestañas de las herramientas que comparten una entrada del menú | — |

Notas:
- Una entrada del menú que agrupa herramientas lleva `['/otra-ruta']` como sexto elemento en `herramientas_proyecto()`; se ve si el visitante puede usar cualquiera de sus rutas.
- `Classes\Pdf` no cambia la fuente de quien no la fijaba: los que dependían del valor por defecto de Dompdf pasan `'defaultFont' => 'serif'`.

## JavaScript (`public/vendor/`)

| Archivo | Para qué |
|---|---|
| `kit-edicion.js` → `window.KitEdicion` | `historial({foto, aplica})` (deshacer/rehacer por fotos: `foto()` devuelve `{k, ...}`), `atajos({deshacer, rehacer, activo, antes})` (Ctrl+Z/Y/Mayús+Z), `avisoSalir({sucio, guardar, limpiar})`, `rasterizar(url, maxLado)`, `masCercano(valor, candidatos, umbral, ultimoGana)` (imán a guías), `renombrarEnLinea(elemento, {valor, max, clase, alTerminar})` (renombrar una fila con doble clic), `listaOrdenable(raiz, {item, clave, claseArrastrando, claseArriba, claseAbajo, alSoltar})` (reordenar filas arrastrando), y utilidades `esc`, `post`, `debounce`, `guardado/guarda` (localStorage). Cárgalo **antes** del motor (`vplan-diseno.js`, `vplan-editor.js`, `estampas-editor.js`). |
| `desktop/src/api.js` | `json()`, `pagina()`, `leer()` y `enviar()` (POST de formulario con CSRF) para hablar con Project Web con la sesión del admin | 9 copias de `json()` |
| `desktop/src/lotes.js` → `porLotes({rutaApi, textos, registrar, resultadoSiVacio, ...})` | Recorrido común de las herramientas de IA por lotes (`/contexto` → modelo → `/resultado`). Lo usan `inconsistencias.js`, `analisis-materiales.js` y `materiales-inteligentes.js` | 3 runners clonados |
| `notas.js` → `window.Notas.iniciar({campo, id, puedeEditar, notas, urlGuardar, urlEliminar})` | Lista de notas y modal; el modal sale de `views/templates/_notas-modal.php`. |

El editor Konva (`vplan-editor.js`) conserva su propio historial por *commits* (`state.past/future`) porque cada lámina del Editor guarda el suyo.

**Lo que NO se comparte entre los motores, a propósito:** guías, reglas, zoom y panel de capas. El Konva trabaja en píxeles del dibujo con escala y desplazamiento propios (guías `{pos px, locked}`), el modo diseño en porcentaje de la hoja con zoom de CSS (guías `{pos %, bloqueada, nombre}`) y las estampas con zoom de CSS y scroll. Compartirlos exigiría adaptadores del tamaño del código que se quitaría, y los datos guardados tienen formatos distintos. Solo desaparecerían copias si los motores se funden (estampas dentro del Konva).

## SCSS (`src/scss/componentes/`)

Estilos que comparten varias herramientas (antes, copias en `src/scss/paginas/`). `app.scss` los carga **antes** de `paginas/`, así que cada herramienta puede ajustarlos encima.

| Pieza | Clases | Para qué | Quién la usa |
|---|---|---|---|
| `_tabs.scss` | `.pestanas` (alias `.vp-pestanas`, `.ve-pestanas`, `.rd-pestanas`, `.sf-pestanas`) | Barra de pestañas de página; la activa se marca con `.activo`/`.activa` o `aria-selected` | vPlan, Editor de marcas, Redactor, SheetFlow |
| `_paneles.scss` | `.pn-cab`, `.pn-cab-ico`, `.pn-cab-txt`, `.pn-cab-acc` | Cabecera de panel lateral (icono + título + acciones) | vPlan (modo diseño), Editor de marcas, Redactor («Páginas» y «Panel») |
| | `.pn-tabs` (alias `.ve-tabs`, `.rd-tabs`), `.pn-cont` | Pestañas del panel: píldoras de 2×2 con contador | ídem |
| | `.pn-busca` | Buscador con lupa (envuelve `input[type=search]` o `type=text`) | vPlan, Editor de marcas, Símbolos |
| | `.pn-chips`, `.pn-chip` (alias `.sim-chip`) | Filtros de categoría en píldora | vPlan, Símbolos |
| | `.pn-grid`, `.pn-ficha` | Rejilla de elementos insertables (icono + nombre + para qué sirve); la ficha se pulsa o se arrastra | vPlan |
| | `.pn-secc`, `.pn-tit` (alias `.vp-cap-tit`), `.pn-vacio` | Sección plegable (`[hidden]`), título de sección y estado vacío | vPlan |
| | `.pn-abrir` (`data-etq` = rótulo vertical) | Pestaña lateral que reabre un panel oculto | vPlan y Editor de marcas |

**Patrón de panel ocultable** (vPlan y Editor de marcas): la cabecera lleva un botón que esconde el panel, agrega una clase a la retícula (`vp-dis-fuera` en `.vp-dis-wrap` / `ve-sin-lateral` en `.ve-area`), muestra `.pn-abrir` y guarda la decisión en `localStorage` (`vp-dis-panel` / `ve-panel`); al elegir un elemento en la hoja el panel se reabre solo (`seleccionar()` en `vplan-diseno.js`).

**Panel con cabecera y pestañas pegajosas** (vPlan): cabecera + pestañas van dentro de `.pn-cab-bloque`, que es lo pegajoso; su **alto se mide con JS** y se publica en la variable `--pn-cab-alto` del panel, para que lo que va debajo (p. ej. la barra de búsqueda de «Insertar», `top: var(--pn-cab-alto)`) se detenga justo debajo y nunca se monte encima. **Las pestañas no llevan alto fijo**: si ocupan dos filas, la caja crece (con altura fija la segunda fila se salía y se montaba sobre el contenido).

`public/vendor/selector-color.js` → `window.VPColor.iniciar(raiz?)`: la **paleta de colores de la plataforma** para todos los `input[type=color]` (básicos, maestra Pantone, mis colores, gotero). Los paneles que se re-dibujan la llaman con su raíz (p. ej. `VPColor.iniciar(document.getElementById('vp-dis-campos'))`); los listeners globales se registran una sola vez.

## Estado vacío ilustrado y tour de guía rápida (2026-10-25)

- **`estado_vacio()`** (`includes/funciones.php`, estilos `.estado-vacio` en `componentes/_estados.scss`): icono + título + texto + hasta dos acciones (y `$extra` para HTML propio, `$clase` para la variante `estado-vacio-sm`). Sustituye al «Aún no hay…» en gris; con `$puedeEditar` se le pasa la acción de crear. Lo usan vPlan (lista, gestor, tablas, hoja sin bloques), Editor y Símbolos. En la misma hoja están `.guia-tarjetas` (tarjetas de ayuda dentro de un modal).
- **`public/vendor/tour.js`** → `window.Tour`: guía paso a paso con spotlight. `Tour.registrar(id, { auto, pasos: [{ sel, titulo, texto, icono, alEntrar }] })`; se dispara solo la primera vez por usuario y herramienta (`localStorage «tour-<id>»`), con el botón `[data-tour="id"]` («Guía rápida») o con `?tour=1`. Un paso sin `sel` sale centrado; `alEntrar` abre pestañas/paneles antes de destacarlos. Estilos en `componentes/_tour.scss`. Registrados: `vplan-diseno` (ver.php), `editor-canvas` (lienzo, vPlan y Editor) y `simbolos`.
- **Asistentes de creación** (`componentes/_asistentes.scss`): `.pasos` + `.paso-tab` (barra 1·2·3) y `.tamano-cards` + `.tamano-card` (tarjetas de tamaño con la proporción dibujada que sincronizan un `<select>` oculto). Los usan «Nueva lámina de vPlan» (3 pasos) y «Nuevo gráfico» del Editor.

## Pendiente de la reestructuración
- Guías, reglas, zoom y panel de capas siguen separados en los motores (ver arriba el porqué). El imán, el renombrado en línea y el reordenado ya son compartidos.
- Fusionar el editor de estampas dentro del Konva (hoy son pestañas hermanas bajo «Editor»).
- Base común para las acciones de `RedactorController` y `SheetflowController` (crear, abrir, duplicar, eliminar); hoy solo comparten `enviar_descarga()`.
- Un solo validador de SVG: `SvgSeguro::limpiar` *limpia* (símbolos) y `EscanerArchivos::svgSeguro` *rechaza* (Archivo). Unificarlos cambiaría qué SVG se aceptan.
- Los demás flujos de IA (Programador, Ensamblador, Ordenar) siguen con su propio aceptar/revertir; `CentroIA::aceptar/rechazar` solo despacha Inconsistencias y Análisis de materiales.
- Subidas de imágenes sueltas (`guardar_imagen_subida`, `Imagenes::*`, View360 `panorama`, materiales…) y las 5 formas de miniatura/base64 para PDF.
- Revisado y descartado: las tres consultas de «restricciones activas» (Lookahead, Planificador, Programador) devuelven datos distintos, no son un duplicado; Comparador y Líneas base, Plantillas y RedactorPlantillas, Ensamblador (IA) y Unir documentos (PDF) son herramientas distintas con nombres parecidos.
