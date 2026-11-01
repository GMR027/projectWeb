# Comparador de documentos

Empareja dos PDF o imágenes para revisar cambios entre revisiones, con deslizador, fundido y diferencias con tolerancia. Las fuentes pueden ser archivos del proyecto, **láminas de vPlan**, **gráficos del Editor**, **documentos del Redactor** (convertidos a PDF) o archivos externos. Incluye anotaciones con hilo de revisión, medición con escala, análisis de páginas, historial de versiones y enlace público de solo lectura.

## Piezas

| Archivo | Qué hace |
|---|---|
| `controllers/ComparadorController.php` | Página, catálogo y entrega de fuentes (`/comparador/fuente`), vista pública, compartir/Telegram, historial, copias guardadas, guardar/eliminar/duplicar y miniaturas |
| `classes/Comparador.php` | Validación de configuración y anotaciones, subida segura de externos, revisiones anteriores, miniaturas, versiones, token público, duplicado y peso en disco |
| `models/ComparadorGuardado.php` | Tabla `comparador_guardados`, tarjetas de la lista y configuración para el navegador |
| `models/ComparadorVersion.php` | Tabla `comparador_versiones` (estado anterior de cada comparación guardada) |
| `views/comparador/index.php` | Fuentes A/B, barra de anotaciones, lista, diálogos de guardado/compartir/historial/páginas/informe y exportación |
| `views/comparador/publico-no-disponible.php` | Enlace caducado o revocado |
| `public/vendor/comparador.js` | Motor de comparación, diferencias, anotaciones, medidas, páginas, versiones, compartir y PDF con jsPDF |
| `public/vendor/anotaciones.js` | Motor compartido de formas (dibujo, selección, medidas, áreas, polígonos y volúmenes) que usan el Comparador y el Anotador |
| `src/scss/paginas/_comparador.scss` | Estilos de la comparación, anotaciones, guardadas compactas y diálogos |

## Fuentes

- `Comparador::fuentes($pid)` arma el catálogo agrupado que pinta el selector **Del proyecto**: archivos de `Archivo`, láminas de `vplan_laminas` (su `marcas_png` o, si falta, el `plano`), láminas de `editor_laminas` con `marcas_png` y documentos de `redactor_documentos`.
- En **Archivo**, cada elemento incluye `prev` cuando existe otra revisión del mismo documento (se agrupan por nombre sin «Rev. A/R2» y se ordenan por número de revisión). El botón **Usar la revisión anterior en B** carga esa revisión con un clic.
- `Comparador::fuenteDeProyecto($pid, $tipo, $id)` valida que el documento pertenezca al proyecto y lo entrega. El Redactor se convierte a PDF con `Redactor::pdf()`; vPlan y Editor usan la imagen ya guardada de la lámina.
- `/comparador/fuente?proyecto=…&tipo=archivo|vplan|editor|redactor&id=…` exige `puede_descargar()` y sirve el documento inline (nunca como archivo estático). Los tipos válidos están en `Comparador::FUENTES_PROYECTO`.
- Las fuentes del proyecto **se referencian, no se copian**: si la lámina o el documento cambia, la comparación guardada muestra la versión actual. Solo los archivos externos se copian al guardar.
- Al guardar se validan igual que al abrir (`fuenteValida()`): un id de otro proyecto o una lámina sin imagen se rechazan.

## Comparación

- Modos: **deslizador** (vertical/horizontal), **fundido** y **diferencias**.
- **Diferencias** se calcula en el navegador a 1600 px de lado: compara A con B ya alineada (escala, giro y traslación) y pinta en rojo los píxeles que superan la **tolerancia** (0–100). Muestra el **porcentaje de área cambiada**, agrupa las celdas cambiadas en zonas y permite saltar con **‹ / ›** entre ellas.
- La rueda hace zoom; el arrastre desplaza; **pellizco, dos dedos y doble toque** funcionan en tablet y teléfono con `public/vendor/gestos-lienzo.js`.
- **Mover B** alinea manualmente B sobre A; **Ajustar B a A**, **Restablecer B** e **Intercambiar** completan el ajuste. Escala, giro, X e Y también se escriben a mano.

## Anotaciones

- Viven en un `<canvas id="cmp-anot">` que ocupa la **ventana** de comparación (no el documento), encima de A y B. Se dibuja con la misma transformación de la vista y a la densidad de la pantalla (`devicePixelRatio`), así los globos, textos y tiradores se ven nítidos con cualquier zoom.
- Las formas se guardan en **coordenadas del documento A** y llevan su **página**. Al cambiar de página ya **no se borran**: cada una se dibuja y se lista solo en su hoja (la casilla «Todas las páginas» las muestra todas en la lista).
- Tipos: `globo` (numerado con texto y línea guía), `texto`, `resaltador` (mano alzada), `resaltado` (rectángulo), `flecha`, `linea`, `rect`, `elipse`, `nube` (nube de revisión), `medir`, `area` y `mano` (mover la vista).
- **Medir y área:** «Calibrar» pide dibujar una cota conocida y su medida real en metros; a partir de ahí todas las medidas se etiquetan en m/cm/mm y las áreas en m²/cm². La escala se guarda con la comparación.
- **Hilo de revisión:** cada globo/texto guarda autor, fecha, estado (`abierta`/`resuelta`) y notas (respuestas). La lista se filtra por todas/abiertas/resueltas y el detalle permite responder, resolver, mover a otra página, duplicar y **crear un pendiente de Punch list o un RFI** precargados.
- Selección múltiple con **Mayús+clic**; se mueven juntas y se pueden **agrupar** para que un clic seleccione todo el grupo. `Ctrl+C`, `Ctrl+V` y `Ctrl+D` copian, pegan y duplican; `Supr` borra. **Modo presentación** oculta las barras, pone la vista en pantalla completa y salta entre anotaciones con ← →.
- Se normalizan en el servidor con `Comparador::anotaciones()` (máx. 300 formas, coordenadas acotadas, colores hex, textos de 600 caracteres, notas de 1000). El JSON guarda `ancho`, `alto`, `escala`, `unidad`, `tamanos` por página y `shapes`; al abrir se reescala cada página con su tamaño.
- Al cambiar el documento A o intercambiar A/B se piden confirmación y se borran las anotaciones (pertenecen a esa hoja); al cambiar de página se conservan.
- El PDF (`Incluir anotaciones, medidas y leyenda`) dibuja las formas y añade una leyenda con globos, estado, notas y medidas.

## Páginas e informe

- **Páginas** analiza hasta 80 páginas de A contra la misma página de B (a 260 px) con la tolerancia actual, muestra una miniatura y el porcentaje de cambio de cada una, y salta a la hoja al pulsar. **PDF de cambios** genera un archivo con una hoja por cada página que cambió.
- **Informe** resume documentos, área cambiada, anotaciones abiertas/resueltas, medidas, páginas analizadas y el listado de globos. Se puede descargar como PDF.

## Guardado, versiones y enlace público

- Tabla `comparador_guardados`: `proyecto_id`, `nombre`, `descripcion`, `a_json`, `b_json`, `vista_json`, `ajuste_json`, `modo_json`, `anotaciones_json`, `miniatura`, `token`, autor y fechas.
- Fuente del proyecto: `{tipo: "archivo", id, nombre, ext, pagina}`.
- Fuente externa guardada: `{tipo: "subido", archivo, nombre, ext, pagina}`. El archivo vive en `public/uploads/comparador/` con nombre aleatorio de 32 hex; la miniatura en `public/uploads/comparador/min/`.
- Solo se suben externos al pulsar **Guardar**. Se revisan con `EscanerArchivos`, se limita a **60 MB por documento**, se comprueba `Almacenamiento::cabe()` y se descuentan del proyecto. Las imágenes se re-codifican con Intervention (`Imagenes::guardarEn`).
- Los archivos se sirven por `/comparador/guardado/archivo` (con permiso de descarga del proyecto), nunca como estáticos. Al eliminar una comparación se borran sus copias y su miniatura.
- **Historial**: al volver a guardar se conserva el estado anterior en `comparador_versiones` (máx. 20 por comparación). Se consulta y restaura desde **Historial**; restaurar también guarda el estado actual para poder deshacer.
- **Compartir** genera un token de 48 hex (`comparador_guardados.token`, único) y un enlace `/comparador/publico?t=…` que abre la comparación **sin sesión y en solo lectura** (sin guardar, anotar ni ver la lista). **Revocar** lo desactiva. **Enviar por Telegram** manda el enlace y, opcionalmente, el PDF a los destinos del proyecto.
- La vista pública sirve los documentos por `/comparador/publico/fuente?t=…&slot=a|b` y no revela rutas internas.
- Los respaldos del proyecto incluyen los archivos porque sus nombres aparecen en los JSON de la tabla.

## Permisos

- Ver/abrir: administrador y clientes con acceso al proyecto (`puede_descargar()`); los visitantes no ven la lista de guardadas.
- Editar, eliminar, compartir o restaurar versiones: el autor (`usuario_id`) o el administrador. El resto puede ver, exportar y **duplicar** (la copia es suya y copia también los documentos externos).
- La vista pública no usa sesión: el token es la autorización y solo muestra esa comparación.
- Rutas: `/comparador`, `/comparador/publico`, `/comparador/publico/fuente`, `/comparador/fuente`, `/comparador/archivo`, `/comparador/guardado/archivo`, `/comparador/miniatura`, `/comparador/guardar`, `/comparador/eliminar`, `/comparador/duplicar`, `/comparador/compartir`, `/comparador/telegram`, `/comparador/versiones`, `/comparador/restaurar`.

## Migraciones

- `database/migracion_comparador_guardados.sql` crea la tabla base.
- `database/migracion_comparador_revisiones.sql` añade `token` a `comparador_guardados` y crea `comparador_versiones`.

## Pruebas

- `php tests/run.php`: valida el esquema, las rutas, las herramientas de anotación, gestos, medidas, diferencias, historial, vista pública y la presencia de la capa de guardado.
- `php tests/integracion.php`: guarda una comparación, valida medidas/notas/tamaños por página, crea y restaura una versión, genera y revoca el enlace público y entrega sus fuentes.
- Prueba manual: dibujar cada tipo, cambiar de página (las anotaciones deben conservarse), calibrar y medir, mover la tolerancia y navegar cambios, analizar páginas, guardar dos veces y restaurar una versión, compartir el enlace y abrirlo sin sesión, enviar por Telegram y exportar el PDF.
