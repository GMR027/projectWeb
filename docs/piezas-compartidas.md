# Piezas compartidas (reestructuración 2026-10)

Antes de agregar código nuevo, revisa si ya existe aquí. Cada pieza reemplazó copias que vivían en varios controladores, clases o editores.

## PHP

| Pieza | Para qué | Reemplazó |
|---|---|---|
| `json_respuesta($datos, $codigo)` (`includes/funciones.php`) | Responde JSON y termina | 29 `json()` privados en controladores |
| `proyecto_api('admin'\|'gestionar'\|'ver')` | Proyecto de una API o 403 en JSON | `proyectoApi()` de 8 controladores de IA |
| `Classes\Pdf::generar/preparar/guardar` | Único lugar donde se arma Dompdf (fuente por defecto DejaVu Sans; el resto de opciones las pasa quien llama) | 18 `new Dompdf` |
| `Classes\ShellTools` | `disponible()`, `ejecutar()` (con tiempo y tamaño máximos), `correr()`, `unirPdf()`, `libreOffice()`, `pdfATexto()` | 5 revisiones de «¿está instalado?» y 6 `proc_open` |
| `Classes\AccesoDb` (trait) | `db()`, `filas()`, `esc()`, `corto()` para clases estáticas | copias privadas en 28 clases |
| `Archivo::guardarArchivoSubido/carpetaCreada/nombreGuardado/reemplazarContenido` | Guardar un archivo en la carpeta del proyecto | 3 flujos de guardado en el Archivo |
| `Classes\MarcasLienzo` | Validar marcas y PNG que llegan del editor y subir imágenes (vPlan «Editar marcas» y Editor) | `guardarMarcas`/`subirImagen` duplicados |
| `Classes\NotasApi` | Crear, editar y borrar notas de una lámina (vPlan) o de un gráfico (View360) | `VplanNotaController` y `View360Controller::nota*` |
| `pestanas_herramienta($grupo, $activa, $proyecto)` (`views/templates/herramientas-proyecto.php`) | Pestañas de las herramientas que comparten una entrada del menú | — |

Notas:
- Una entrada del menú que agrupa herramientas lleva `['/otra-ruta']` como sexto elemento en `herramientas_proyecto()`; se ve si el visitante puede usar cualquiera de sus rutas.
- `Classes\Pdf` no cambia la fuente de quien no la fijaba: los que dependían del valor por defecto de Dompdf pasan `'defaultFont' => 'serif'`.

## JavaScript (`public/vendor/`)

| Archivo | Para qué |
|---|---|
| `kit-edicion.js` → `window.KitEdicion` | `historial({foto, aplica})` (deshacer/rehacer por fotos: `foto()` devuelve `{k, ...}`), `atajos({deshacer, rehacer, activo, antes})` (Ctrl+Z/Y/Mayús+Z), `avisoSalir({sucio, guardar, limpiar})`, `rasterizar(url, maxLado)`, y utilidades `esc`, `post`, `debounce`, `guardado/guarda` (localStorage). Cárgalo **antes** del motor (`vplan-diseno.js`, `vplan-editor.js`, `estampas-editor.js`). |
| `notas.js` → `window.Notas.iniciar({campo, id, puedeEditar, notas, urlGuardar, urlEliminar})` | Lista de notas y modal; el modal sale de `views/templates/_notas-modal.php`. |

El editor Konva (`vplan-editor.js`) conserva su propio historial por *commits* (`state.past/future`) porque cada lámina del Editor guarda el suyo.

## Pendiente de la reestructuración
- Guías, reglas, imantado, capas y zoom siguen escritos por separado en los tres motores (cambian el formato de guardado: px en Konva, % en el modo diseño).
- Fusionar el editor de estampas dentro del Konva (hoy son pestañas hermanas bajo «Editor»).
- Base común para `RedactorController` y `SheetflowController`; `Sheetflow::importar` sobre `TablaDeDocumento`.
- `Restricciones::activas()` único (hoy en Lookahead, Planificador y Programador).
- Clase base para Inconsistencias y Análisis de materiales y runner de lotes único en la app de escritorio.
- Un solo validador de SVG: `SvgSeguro::limpiar` *limpia* (símbolos) y `EscanerArchivos::svgSeguro` *rechaza* (Archivo). Unificarlos cambiaría qué SVG se aceptan.
