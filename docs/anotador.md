# Anotador de planos y documentos

Herramienta `/anotador` (menú **Documentos y diseño**). Abre un PDF o una imagen y trabaja **encima** del documento: anota, mide, calcula áreas y volúmenes, inserta imágenes, estampas inteligentes y firmas, y descarga el PDF con todo aplanado. Es distinta del **Editor** (crea dibujos desde cero) y del **Comparador** (enfrenta dos revisiones).

## Piezas

| Archivo | Qué hace |
|---|---|
| `controllers/AnotadorController.php` | Página, entrega de la fuente, guardar/eliminar/duplicar, versiones, imágenes, firmas, estampas, finalizar y reabrir |
| `classes/Anotador.php` | Validación, copia de externos, normalización de anotaciones y configuración, miniaturas, versiones, firma, almacenamiento y limpieza de huérfanos |
| `models/AnotadorDocumento.php` | Tabla `anotador_documentos` (documento base + capa de anotaciones) |
| `models/AnotadorVersion.php` | Tabla `anotador_versiones` (máx. 20 por documento) |
| `views/anotador/index.php` | Pantalla de **documentos** (listado + abrir/crear) y, al abrir uno, el editor con su paleta de herramientas, paneles y diálogos |
| `public/vendor/anotador.js` | Motor del Anotador: documento, páginas, zoom, interacción, firmas, estampas y PDF |
| `public/vendor/anotaciones.js` | **Motor compartido** de formas con el Comparador (ver `docs/piezas-compartidas.md`) |
| `src/scss/paginas/_anotador.scss` | Estilos (visor, paneles, capa de texto, diálogos y móvil) |

## Interfaz

- `/anotador` sin `?id` es la **pantalla de documentos**: una tarjeta para abrir o crear (fuente del proyecto o archivo externo) y, aparte, el **listado de documentos guardados** con buscador, miniatura, autor, número de anotaciones/páginas y acciones.
- Al abrir un documento (`?id=…`) la pantalla cambia al **editor** (no se mezcla con el listado); la cabecera y el pie tienen el enlace **Documentos** para volver.
- La paleta agrupa **todas** las herramientas con nombre y tecla: Básico (seleccionar, mover), Marcar (globo, texto, resaltador, zona, flecha, línea, rectángulo, elipse, nube), Medir (medir, área, polígono, volumen), Insertar (imagen, estampa, firma) y Texto del PDF (resaltar, subrayar, tachar). La barra superior queda para zoom, estilo, historial y guardar/exportar/firmar.
- Los diálogos usan `<dialog>` y las variables del tema (`--surface`, `--text`, `--bg-secondary`), por lo que respetan el tema claro/oscuro elegido por el usuario; también el editor de texto flotante.
- Por debajo de 64 rem las páginas y el panel se abren como hojas superpuestas sobre el visor; por debajo de 48 rem la paleta se muestra solo con iconos (todos envueltos en varias filas, nunca ocultos en un scroll horizontal).

## Fuentes

- `Anotador::fuentes()` y `Anotador::fuenteDeProyecto()` reutilizan el catálogo del Comparador: Archivo, láminas de vPlan, gráficos del Editor y documentos del Redactor (convertidos a PDF).
- `/anotador/fuente?proyecto=…&doc=ID` entrega la fuente de un documento guardado (incluye la copia externa). `/anotador/fuente?proyecto=…&tipo=archivo|vplan|editor|redactor&id=…` entrega una fuente del proyecto para abrirla como borrador.
- Los archivos externos se leen en el navegador mientras se anota y se copian al proyecto **solo al guardar** (PDF con nombre aleatorio o imagen re-codificada). Límite: 80 MB.
- Las fuentes del proyecto **se referencian, no se copian**: si la lámina o el documento cambia, al reabrir se usa la versión actual.

## Anotaciones

- Motor compartido `window.Anotaciones`: dibujo, selección, tiradores, movimiento, escala, medidas y áreas. El Comparador lo usa desde la extracción (Fase 0) y el Anotador añade tipos nuevos.
- Tipos del Anotador: `globo`, `texto`, `resaltador`, `resaltado`, `flecha`, `linea`, `rect`, `elipse`, `nube`, `mano`, `medir`, `area`, `poligono`, `volumen`, `imagen`, `estampa`, `firma`, `resaltado_texto`, `subrayado` y `tachado`.
- Las coordenadas viven en píxeles de la página y cada forma lleva su `pagina`. El servidor normaliza en `Anotador::anotaciones()`: máx. 500 formas, coordenadas acotadas, colores hex, textos de 600 caracteres, notas de 1000 y profundidad de 0 a 10 000 m.
- La configuración `config_json` guarda `escalas` por página, `unidad` y el estado de firma. **No hay enlace público**: el acceso exige sesión y permiso del proyecto.

## Medidas, áreas y volúmenes

- **Calibrar página**: dibuja una cota conocida y escribe los metros. Se guarda `escala[página] = m/px`; cada hoja puede tener una escala distinta. La línea de calibración se descarta.
- **Polígono**: clic por vértice y doble clic o Enter para cerrar. Área por fórmula del polígono (shoelace), perímetro por suma de lados. La etiqueta puede mostrar solo el área o área y perímetro (Propiedades).
- **Volumen**: polígono × profundidad en metros. El resultado se etiqueta en m³/cm³ y se puede cambiar después en Propiedades.
- El panel **Medidas** lista distancia, área, perímetro, profundidad y volumen con totales por proyecto. Exporta **CSV** y copia el resumen. El PDF añade una hoja de leyenda con las medidas.
- `Anotaciones::fmtLong/fmtArea/fmtVolumen` respetan metros, centímetros y milímetros según la magnitud.

## Imágenes, estampas y firmas

- **Imágenes**: se suben desde el panel Insertar (archivo o cámara), se re-codifican con `Imagenes::guardarEn('anotador', …)` y se sirven por `/anotador/imagen?proyecto=…&archivo=…` (con permiso del proyecto, incluso antes de guardar). Al insertarlas se copian al proyecto y cuentan para `Almacenamiento`.
- **Estampas inteligentes**: `/anotador/estampas` lista las del proyecto y `/anotador/estampa?id=…` rasteriza una con Dompdf + `PdfRaster` (caché diaria por configuración). Se inserta el PNG y se puede volver a insertar para refrescarla cuando cambien los datos.
- **Firmas**: biblioteca personal del Redactor (`redactor_firmas`) reutilizada; también se puede dibujar o escribir una nueva. Al colocarla se guarda una copia visible en el proyecto (`anotador/`) con nombre, cargo y fecha. La firma puede llevar el texto «✓ firmado». Las firmas de la biblioteca se sirven por `/anotador/firma-ver` para no depender de que el módulo Redactor esté visible; al guardar se recorta el margen transparente y se coloca con su proporción real.
- **Finalizar firma**: exige al menos una firma colocada, guarda la versión anterior, marca `config.finalizado`, calcula la huella SHA-256 del estado y registra cada firma en `anotador_firmas_aplicadas`. El documento queda bloqueado (solo lectura) y se descarga el PDF. **No es una firma criptográfica**: es una firma visible con trazabilidad. Solo el administrador puede reabrirlo (`/anotador/reabrir`).
- **Resaltar texto**: en PDF con texto seleccionable, `pdfjsLib.renderTextLayer` monta la capa de texto y la selección se convierte a rectángulos en coordenadas de página para `resaltado_texto`, `subrayado` y `tachado`. En planos escaneados se usa el resaltador rectangular.

## Guardado, versiones y PDF

- Tabla `anotador_documentos`: `proyecto_id`, `nombre`, `descripcion`, `fuente_tipo`, `fuente_id`, `archivo` (copia externa), `nombre_archivo`, `ext`, `paginas`, `config_json`, `anotaciones_json`, `miniatura`, autor y fechas.
- Cada guardado de un documento existente crea una fila en `anotador_versiones` (máximo 20) y se puede restaurar; restaurar guarda antes una copia del estado actual.
- `POST /anotador/guardar` recibe `FormData` (nombre, descripción, páginas, configuración, anotaciones, miniatura y, si es nuevo, `documento`); responde `{success, id}`. `POST /anotador/firmar` finaliza; `POST /anotador/reabrir` solo admin.
- **PDF**: se genera en el navegador con jsPDF (ya vendorizado). Cada página se rasteriza y se le dibujan las anotaciones a la misma escala; el resultado conserva el tamaño original de la hoja. Si hay medidas o firma se agrega una hoja de leyenda con el cuadro y la huella.
- También se generan miniaturas de cada documento para la lista (`/anotador/miniatura`).

## Permisos

- Ver/abrir: sesión con acceso al proyecto y `puede_descargar()`; los visitantes no ven la lista ni el selector.
- Editar/guardar/eliminar/duplicar: `puede_descargar() && !is_visitante()`; el documento lo modifica quien lo creó o el administrador. Un documento finalizado es de solo lectura.
- Eliminar: quien lo creó o el administrador (aunque esté firmado).
- La ruta `/anotador` está declarada en `Usuario::HERRAMIENTAS`; las subrutas heredan la herramienta. El archivo `tests/run.php` comprueba que las rutas del menú estén cubiertas.

## Rutas

`/anotador`, `/anotador/fuente`, `/anotador/imagen` (GET y POST), `/anotador/miniatura`, `/anotador/guardar`, `/anotador/eliminar`, `/anotador/duplicar`, `/anotador/versiones`, `/anotador/restaurar`, `/anotador/firmas`, `/anotador/firma`, `/anotador/firma-ver`, `/anotador/firmar`, `/anotador/reabrir`, `/anotador/estampas`, `/anotador/estampa`, `/anotador/simbolos`.

## Almacenamiento, medios y respaldos

- Carpeta propia `public/uploads/anotador/`, con `min/` para miniaturas. `Classes\Media` reconoce la carpeta `anotador` (incluidas las miniaturas) y `Almacenamiento::desglose()` muestra «Anotador (planos y firmas)».
- Los archivos PDF externos y las imágenes **no** se sirven como estáticos: los entrega el controlador (fuente) o `/anotador/imagen` (imágenes).
- Los respaldos del proyecto incluyen las tablas por la FK a `proyectos` y los archivos porque sus nombres aparecen en los JSON de `anotador_documentos`/`anotador_versiones`.
- `Anotador::limpiarHuerfanos()` borra imágenes y copias sin referencia tras 24 h (se ejecuta ocasionalmente al abrir la herramienta).

## Migración

`database/migracion_anotador.sql` crea `anotador_documentos`, `anotador_versiones` y `anotador_firmas_aplicadas`; también están en `database/schema.sql`. **Aplicar en el clúster.**

## Pruebas

- `php tests/run.php`: esquema/rutas/menú, motor compartido, polígonos/volúmenes/firmas, capa de texto, exportación, Media y almacenamiento, y el normalizador de `Anotador::anotaciones()`.
- `DB_NAME=project_ci_anotador php tests/integracion.php`: guardar, versionar, restaurar, finalizar (huella + registro), rechazar guardado tras firmar, reabrir, duplicar y eliminar.
- Prueba manual: abrir un PDF con texto, resaltar, calibrar, dibujar polígono y volumen, insertar imagen/estampa/firma, guardar, recargar, exportar PDF y finalizar la firma.

## Pendiente

- Firma criptográfica/certificado digital real (hoy es firma visible con huella interna).
- Actualización automática de estampas ya colocadas (hoy se vuelven a insertar).
- Edición de vértices del polígono después de cerrarlo (hoy se mueve/escala completo).
- Enlace público de solo lectura (descartado en esta fase).

## Estilos, polilínea, capas y símbolos (2026-11-11)

- **Estilo desde el inicio:** barra con color, grosor 1–24, opacidad y tipo de línea (`solida`, `punteada`, `guiones`, `guion_punto`); se guarda en cada forma y se aplica a lo nuevo y a lo seleccionado. El motor compartido `anotaciones.js` dibuja el trazo y multiplica la opacidad de la forma por la de la capa.
- **Polilínea:** herramienta `polilinea` (tecla Y), abierta y con 2+ vértices; doble clic o Enter termina, Retroceso quita el último. No calcula área.
- **Capas:** `config.capas` (`id`, `nombre`, `visible`, `bloqueada`, `opacidad`) y `shape.capa`. Panel Capas para crear, renombrar, ordenar, ocultar, bloquear y atenuar; la capa activa recibe lo nuevo. Las capas ocultas no se dibujan, no se seleccionan, no salen en el PDF ni en las medidas; las bloqueadas se ven pero no se editan. Los documentos viejos usan una capa «Anotaciones».
- **Símbolos:** `GET /anotador/simbolos` devuelve `Simbolo::paraEditor()` (proyecto + materiales + predeterminados). Se rasterizan (también SVG), se copian a `uploads/anotador` y se insertan como `tipo = simbolo` con `simbolo_id` y `nombre`.
- Rutas nuevas: `/anotador/simbolos`. Pruebas: `tests/run.php` (normalización de polilínea/símbolo y capas) e `tests/integracion.php` (guardado/lectura de capas y símbolos).
