# SheetFlow (tablas reutilizables en vPlan, Editor y Redactor)

_Se lee solo al trabajar en este módulo._

## Qué es
- Herramienta `sheetflow` (menú justo debajo de Redactor; clave en `Usuario::HERRAMIENTAS`, prefijos `/sheetflow` y `/api/sheetflow`). Código: `controllers/SheetflowController.php`, `models/SheetflowHoja.php`, `classes/Sheetflow.php`, vistas `views/sheetflow/`, editor de cuadrícula en `public/vendor/sheetflow.js` (JS plano, sin build), estilos `src/scss/paginas/_sheetflow.scss`. Tabla `sheetflow_hojas` (`database/migracion_sheetflow.sql`, también en `schema.sql`; **aplicar en el clúster**: además añade `vplan_tablas.sheetflow_id`).
- Misma estructura que el Redactor: lista agrupada por departamento, alta (nombre, fecha, departamento, descripción), ver, editar, duplicar, eliminar y descargar (`Y_m_d_Nombre.xlsx|csv|pdf`).

## Datos
`datos` (LONGTEXT JSON, **siempre** pasa por `Sheetflow::limpiar`; máx. 30 columnas, 1 000 renglones, 500 caracteres por celda, 2 MB):
`cols [{n, t tipo, a alineación, tot}]`, `rows [[texto crudo]]`, `calc {"fila,col": resultado}` (resultado de las fórmulas), `tot {col: resultado}` (fila de totales) y `estilo {enc, encTxt, zebra, bordes}`.
- Tipos: `texto, numero, moneda, porcentaje, fecha` (la fecha se guarda ISO). Totales: `suma, promedio, min, max` (solo numéricas) y `cuenta`.
- **Las fórmulas las calcula el cliente** (`sheetflow.js`, evaluador propio sin `eval`): operadores `+ - * / ^ %`, comparaciones, `A1`/`A1:B3` (el encabezado es la fila 1, como Excel) y `SUMA PROMEDIO MIN MAX CONTAR REDONDEAR SI`. El servidor solo guarda el resultado en `calc`; las plantillas con fórmulas traen su `calc` precalculado. Insertar/eliminar renglones y columnas reescribe las referencias (`reescribir()`); ordenar se bloquea si hay fórmulas.
- **Formato:** `Sheetflow::formatear()` (PHP) y `formatear()` (JS) deben dar lo mismo: número ≤4 decimales con miles, moneda `$1,234.50`, porcentaje ≤2 decimales, fecha `d/m/Y`. Si cambias uno, cambia el otro.
- `Sheetflow::matriz($hoja)` es lo que consumen las demás herramientas: columnas, tipos, alineación, renglones **ya formateados** (sin renglones vacíos al final) y fila de totales.

## Formato por celda, imágenes y archivo `.sheetflow`
- `fmt` (mapa disperso `"fila,col"` → formato; fila `-1` = encabezado) se sanea en `Sheetflow::limpiarFmt`: `b i u` (negrita, cursiva, subrayado), `fs` (6–72 px), `tc` (letra), `bg` (relleno), `al` (alineación que manda sobre la de la columna), `bt br bb bl` (borde `grosor|solid|dashed|dotted|double|#color`, o `n` = sin borde) e `im` (imagen). Sin `tc` y con `bg`, la letra se ajusta sola (`contraste()` en PHP y JS: deben coincidir). `cols[].w` = ancho en px (0 = automático).
- En el cliente los formatos viven en matrices paralelas a `rows` (`sty`, `hsty`); **toda operación de estructura (insertar, eliminar, ordenar, pegar) debe mover también `sty`/`hsty`**. Al aplicar un borde se escribe también el lado contiguo de la celda vecina (`ponerBorde`) para que no choquen.
- Render: `Sheetflow::cssFmt` (pt) lo usan la vista, el PDF, el HTML del Redactor y se refleja en XLSX (`formatosXlsx`: fuente, colores, bordes, alineación, anchos, imágenes). vPlan y Editor solo llevan el texto.
- **Imágenes de celda:** `POST /api/sheetflow/imagen` (`Imagenes::guardarGrande`, 1600 px, cuenta en `Almacenamiento`); se guardan como `/uploads/vplan/<hash>`. Al guardar o eliminar la tabla, `Sheetflow::limpiarImagenes` borra del disco las que ya no usa nada (otras tablas o documentos del Redactor del proyecto). Las tablas duplicadas comparten archivo.
- **Archivo propio `.sheetflow`** (JSON con datos e imágenes en base64): descarga «SheetFlow (.sheetflow)» y se importa desde «Nueva tabla». Es una extensión permitida en Archivo (`Archivo::EXTENSIONES`) con icono propio (`fa-table-cells`, clase `ico-sheetflow`, `archivo_icono_html('sheetflow')`) para distinguirla de los Excel.
- **Funciones condicionales** (`SheetflowFormulas` y `sheetflow.js`): `SI Y O NO SI.ERROR SUMAR.SI CONTAR.SI PROMEDIO.SI`, comparaciones de texto, `&`, `VERDADERO/FALSO`. Los errores de valor viajan como valor (`ErrorValor` en PHP) para que `SI.ERROR` los atrape.
- Interfaz: pestañas Inicio / Insertar / Datos / Vista (se recuerda la última en `localStorage sf-pestana`), tamaño de letra, colores, bordes, imagen, selector de funciones, ancho de columna arrastrando, zoom y estadística de la selección.

## Integraciones (vínculos vivos)
- **El servidor calcula las fórmulas:** `classes/SheetflowFormulas.php` (mismo lenguaje que `sheetflow.js`; si cambias uno, cambia el otro). `Sheetflow::normalizar()` = `limpiar` + `recalcular` y es lo que guarda `guardar` y toda escritura desde otras herramientas; el `calc` del cliente se ignora.
- **vPlan:** una `VplanTabla` con `sheetflow_id` lee **en vivo** columnas (máx. 8) y renglones (máx. 200, totales como último renglón) de la hoja (`VplanTabla::hoja()/columnasOtro()/filas()`; si la hoja se borra, valen los datos propios). **Bidireccional:** `apiFila` escribe en la hoja (`Sheetflow::cambiarCelda/quitarFila`); no deja editar celdas con fórmula ni el renglón de totales, no agrega renglones si hay totales y no quita renglones si hay fórmulas. En el formulario de la tabla solo se edita título y nombre. `POST /sheetflow/a-vplan` crea el vínculo; con `tabla` re-vincula/refresca la copia de respaldo. `VplanTabla::duplicar` crea una copia independiente (sin vínculo).
- **Columnas de la tabla en la lámina:** el reparto de anchos lo calcula `VplanDiseno::repartoColumnas()` a partir del contenido (más ancho a la columna que lo necesita, y las de tipo `fecha` un poco más, por el calendario del navegador). Lo comparten el estimador del alto, la vista y el PDF, para que la lámina se vea igual que el PDF.
- **Redactor:** la tabla se inserta con `<table data-sf="id">` (`Sheetflow::htmlRedactor`; `data-sf` permitido en `Redactor::limpiar`). `Sheetflow::refrescarHtml` la rehace al abrir, ver y exportar (DOCX/ODT/PDF); lo guardado se actualiza al guardar de nuevo. **Lo que se edite dentro de una tabla vinculada se pierde**: se edita en SheetFlow.
- **Editor:** forma `table` con `sheetflow: id` (`tablaDeSheetflow()`); `refrescarSheetflow()` la actualiza al abrir el gráfico (solo la lámina activa; queda sin guardar hasta que se guarde, conserva posición/giro/opacidad).
- `EnlacesLamina::destinos()` incluye las tablas (`/sheetflow/ver`).
- Importar: `Sheetflow::importar()` (PhpSpreadsheet; csv/xlsx/ods, primera hoja, 1.ª fila = encabezados; detecta columnas numéricas; CSV en UTF-8 o Windows-1252).

## SheetFlow como fuente única (2026-10-21)
- `Sheetflow::crearHoja($proyecto, $nombre, $matriz|null, $usuario)` crea una hoja (vacía o desde una matriz con encabezado primero); `tablaVplanDe($hoja)` devuelve la tabla de vPlan que ya la muestra o crea una vinculada (nunca duplica); `deTablaVplan($tabla)` pasa una tabla «otro» con datos propios a hoja y la vincula (sus renglones viejos quedan como respaldo).
- **vPlan:** `/vplan/crear` tiene «Tabla inicial» (ninguna, nueva en blanco o una hoja existente; `VplanController::tablaInicial`); `/vplan/crear?sheetflow=ID` la preselecciona (botón «Crear lámina vPlan con esta tabla» en SheetFlow). Las tablas «Otro» nuevas nacen ya como hoja y abren SheetFlow. `POST /vplan/tablas/a-sheetflow` (`id` o `todas=1`) convierte las existentes. `restricciones`, `secundarias`, `punch` y `punchlist` siguen siendo vistas de datos del programa.
- **Redactor:** `POST /api/redactor/hoja` (`accion=nueva|convertir`, `filas` JSON con encabezado primero) crea la hoja y devuelve la tabla `data-sf`. En «Insertar tabla»: origen «Nueva tabla de SheetFlow (en blanco)» y casilla «Como tabla de SheetFlow» para programa/materiales/etc. (`hojaDeMatriz` en `redactor-extras.js`). Con el cursor en una tabla: «Editar en SheetFlow» (si es `data-sf`) o «Pasar a SheetFlow» (si es propia).
- La hoja muestra dónde se usa: tablas de vPlan (`usosVplan`) y documentos del Redactor (`usosRedactor`, busca `data-sf` en el contenido).

## Tabla inteligente y tipos de captura (2026-10-20)
- «Nueva tabla › Tabla inteligente»: constructor guiado (nombre, tipo y color de encabezado por columna, mover, renglones iniciales) → `Sheetflow::inteligente()`; el color va en `fmt["-1,col"].bg`.
- Tipos nuevos: `lista` (`cols[].op`, máx. 20 opciones; se editan en Datos › Columna), `casilla` (guarda `1`/vacío; se muestra ✔) y `semaforo` (`verde|amarillo|rojo`; se muestra Verde/Amarillo/Rojo). `Sheetflow::normalizarValor` y `valorEspecial()` (JS) deben coincidir; `formatear` en PHP y JS también.
- **vPlan:** en una tabla vinculada, `VplanTabla::metaColumnas()` da tipo/opciones por columna y `filasVplan` entrega además el valor crudo `v0..vN`; `_fila-adicional.php` dibuja el control (fecha, número, casilla, lista, semáforo con color) y `cambiarCelda` guarda en la hoja (rechaza valores fuera de la lista). Editor y Redactor muestran el valor formateado (solo lectura).

## Escalado y tiempo real (2026-10-20)
- **vPlan:** `VplanDiseno::medidasTabla()` usa la escala normal y, si la tabla cabe con la letra más grande (9 pt), la hace crecer (hasta 48 pt) hasta llenar el bloque; lo usan la lámina (`_hoja.php`) y el PDF.
- **Editor:** al escalar una tabla con las asas, la letra se multiplica por √(sx·sy) y las filas se reajustan (`aplicarTransformacion`). `refrescarSheetflow()` conserva letra y anchos del usuario, se repite al volver a la ventana y cada 15 s (si no se está escribiendo).
- **Vista de SheetFlow y lámina de vPlan:** `public/vendor/tablas-vivo.js` consulta `/api/sheetflow/version` o `/api/vplan/tablas-version` y recarga la página si cambió algo (no si hay un campo enfocado o un diálogo abierto). La letra de `.sf-ver` sigue el ancho del contenedor (`cqw`).
- **Redactor:** sigue refrescando al abrir/ver/exportar (no se recarga solo para no perder texto sin guardar).

## Exportación
- XLSX con PhpSpreadsheet (encabezado con color, formatos de número, fechas reales, totales en negrita, filas alternas, panel congelado). Las fórmulas se escriben como **valores** (no como fórmulas de Excel). CSV con BOM. PDF con Dompdf (carta; horizontal si hay más de 6 columnas).

## Pendiente
- Varias pestañas por documento, anchos de columna ajustables, formato por celda, relleno arrastrando refrescar todas las láminas del Editor (no solo la activa) y escribir de vuelta desde Editor/Redactor.

- **Carpetas (2026-10-19):** igual que en el Redactor, la carpeta es el `departamento`; renombrar y «Mover a…» desde la lista (`classes/Carpetas.php`, `POST /carpetas/renombrar|mover`).
