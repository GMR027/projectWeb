# vPlan — vPlans (paquetes) de láminas libres de bloques (2026-10-18)

Una lámina ya no depende de una partida: es un **lienzo** (`ancho_mm`, `alto_mm`, `preset`) con **bloques** posicionables.

## vPlans (paquetes) — varios por proyecto
- `vplan_paquetes` (`proyecto_id, nombre, orden`) agrupa las láminas; cada lámina es **un área** con su plano, tablas, título, simbología y distribución. `vplan_laminas` gana `paquete_id`, `visible` (0 = oculta) y `grupo` (disciplina, nivel…; solo organiza el gestor).
- Pantallas: `/vplan` (lista de vPlans), `/vplan/paquete` (gestor: ojo, orden ↑↓, grupo editable, mostrar/ocultar grupo, duplicar, editar, eliminar; botones de icono `.vp-ib`), `/vplan/ver` (con el **navegador de láminas** `_nav-laminas.php`: columna izquierda con miniaturas a escala agrupadas por grupo, buscador, plegable; recuerda su estado en localStorage `vp-nav-oculta`).
- **Oculta** = no sale en listas de visitantes, ni en el PDF «todas» / Exportar todo (`VplanLamina::visiblesDeProyecto`); sí se puede exportar una lámina oculta eligiéndola. No cambia avance ni restricciones.
- **Bloque oculto** (`config.oculto`): no se dibuja ni se exporta; un bloque de tareas oculto tampoco numera restricciones. En modo diseño se ve atenuado y la lista «Capas» del panel tiene ojo por bloque.
- **Duplicar lámina** (`VplanLamina::duplicar`): copia bloques, tamaño, supervisor, grupo, simbología (archivo propio) y **tablas como copias independientes**; NO copia plano ni marcas (se sube el del área nueva).
- Migración: `database/migracion_vplan_paquetes.sql` mete las láminas existentes en un vPlan por proyecto; `VplanPaquete::adoptarHuerfanas` cubre las que queden sin vPlan.

## Modelo
- `vplan_laminas`: título interno, supervisor, `plano`/`simbologia` (archivos), `marcas_*` (editor Konva), `recovery_cols`, `ancho_mm`/`alto_mm`/`preset`. `partida_id` queda como "partida principal" **opcional** (la usan `Renumeracion` y el orden de la lista); no manda sobre el contenido.
- `vplan_bloques` (`lamina_id, tipo, x, y, w, h` en **% de la hoja**, `z`, `config_json`). Tipos (`Model\VplanBloque::TIPOS`): `titulo`, `logo`, `plano`, `tareas`, `tabla`, `simbologia`, `schedule`, `supervisor`, `fecha`, `texto`.
- `config` por tipo (se sanea en `VplanController::configDeBloque`): textos → `texto, tamano, negrita, color, alinear`; `logo` → `cual, alinear`; `tabla` → `tabla_id`; **`tareas` → `fuentes:[{partida, actividades:null|[ids]}], seq:'continuo'|'partida', titulo, color`** (`actividades:null` = toda la partida, dinámica).
- **Recovery dinámico:** `vplan_laminas.recovery_n` = cuántas columnas (máx. `VplanLamina::MAX_RECOVERY` = 6; encabezados `Vplan::etiquetaRecovery`: RECOVERY, RECOVERY 02, 03…). Fechas por (lámina, actividad, n) en `vplan_recovery_n` (`inicio`, `fin`); `VplanLamina::recovery()`/`guardarRecovery($act, $n, $campo, $v)`. `Vplan::datos` entrega `nRec` y por tarea `rec[]` (índice 0 = recovery 1). `POST /vplan/lamina/recovery` (`accion` agregar/quitar la última; `ajax=1` → JSON), `POST /api/vplan/recovery` (`n`, `campo` inicio|fin). Quitar la columna conserva las fechas. `recovery_cols` (máscara) y la tabla `vplan_recovery` quedan en desuso (la migración copia los datos).
- `vplan_lamina_tablas` sigue siendo la pertenencia de tablas; cada tabla de la lámina tiene un bloque `tabla` (`VplanLamina::bloques()` lo crea/retira solo).

## Conversión automática
`VplanLamina::bloques()` convierte, la primera vez que se pide, una lámina sin bloques en el diseño de siempre (`VplanDiseno::porDefecto`: logos+título arriba, plano a la izquierda, tareas y tablas apiladas a la derecha, simbologías y pie abajo; vertical: plano arriba y tablas debajo). Usa `partida_id` + `vplan_lamina_actividades` como fuente inicial (con selección → `seq:'partida'`, igual que antes). Lleva `GET_LOCK` para no duplicar.

## Reglas
- **SEQ:** `continuo` (1..n en el bloque, por defecto) o `partida` (el original dentro de su partida, con una fila de subtítulo por partida si hay más de una).
- **Restricciones:** se numeran en **toda la lámina**, en el orden de lectura de los bloques de tareas (`y`, luego `x`) y el orden de las actividades (`Vplan::restriccionesDe`). Una tabla `restricciones` las muestra. `restr_auto` se reinicia si cambian las fuentes.
- **Letra de tablas:** `VplanDiseno` estima la altura (en espacio de referencia tabloide 432 mm) y elige la escala `e0…e5` más grande que cabe en el bloque; letra real = referencia × (ancho_mm/432). Los anchos de columna (`columnasTareas`, `columnasTabla`) los comparten el estimador, el PDF y la vista (`<colgroup>`): si cambias uno, cambia el otro.
- **PDF** (`VplanPdfExporter` + `views/exportar/pdf-vplan.php`): cada bloque es absoluto en pt; una hoja por lámina con su tamaño. Dompdf no mezcla tamaños: si difieren, un PDF por lámina y se unen con `pdfunite` (poppler-utils, ya en el Dockerfile). `hoja` mide 2 pt menos que la página (si no, Dompdf crea una página en blanco). Tras renderizar, si hay >1 página se baja un paso la letra de las tablas (`extra`, máx. 2).
- **Vista** (`_hoja.php`): contenedor `container-type:inline-size`, letra en `cqw`. **Modo diseño** (`?diseno=1`, `_diseno-*.php`, `public/vendor/vplan-diseno.js`): capa `.vp-ov` por bloque para arrastrar/redimensionar; guarda con `POST /api/vplan/diseno` (JSON completo: `tamano`, `bloques` en orden de z, `guias`; los bloques que faltan se eliminan y una tabla que falta sale de la lámina); `POST /vplan/diseno/restablecer` regenera el diseño estándar conservando las fuentes del primer bloque de tareas y las tablas.

## Modo diseño: interfaz (2026-10-18)
- Barra sticky (guardar, salir, ayudas: Rejilla, Imán, Guías, Reglas; Ctrl+S guarda) + panel derecho con 4 pestañas: **Propiedades** (posición en cm, frente/fondo, duplicar, oculto, **bloqueado**, nombre de capa, campos por tipo), **Capas**, **Insertar** y **Hoja** (tamaño y restablecer). `views/vplan/_diseno-barra.php`, `_diseno-panel.php`, `public/vendor/vplan-diseno.js`, estilos en `src/scss/paginas/_vplan.scss`.
- **Capas:** lista de objetos (arriba = al frente) con arrastrar para reordenar, ▲▼, ojo, candado y doble clic para renombrar (`config.nombre`, `config.bloqueado`, `config.oculto`; se sanean en `configDeBloque`). El orden de la lista es el z (`$b->z` = posición al guardar). Debajo, la lista de **guías**.
- **Guías (estilo Inkscape):** `vplan_laminas.guias_json` = `[{id, eje: v|h, pos (% de la hoja), visible, bloqueada, nombre}]`. Se crean arrastrando desde las reglas (la de arriba da horizontales, la izquierda verticales) o con los botones de Capas; se mueven arrastrándolas (soltar fuera de la hoja la descarta), con flechas o escribiendo la posición en cm. Los bloques se **imantan** (6 px) a las guías visibles y a los bordes/centro de la hoja al mover o redimensionar. No salen en el PDF ni en la vista normal.
- **Insertar:** bloques, **tablas** (todas las de `vplan_tablas` de todos los tipos, agrupadas, con buscador, «Copia independiente», y las hojas de SheetFlow aún sin tabla: `POST /vplan/lamina/tabla` con `sheetflow_id` la crea o reutiliza) y **Recovery** (agregar/quitar columnas). Estas acciones guardan el diseño pendiente, llaman al servidor con `ajax=1` y recargan.
- Migración: `database/migracion_vplan_diseno2.sql` (**aplicar en cada BD**: añade `guias_json`, `recovery_n`, `vplan_recovery_n` y convierte la máscara anterior).

- **Propiedades** (2026-10-18): secciones Posición y tamaño (cm), Orden y acciones (al frente/fondo/duplicar/quitar), Visibilidad (interruptores oculto/bloqueado) y Contenido (campos por tipo, generados en `pintarCampos`). Mantén los ids `vp-pos-*`, `vp-c-oculto`, `vp-c-bloqueado`, `vp-dis-*`: el JS los usa.
- **Botón «Láminas»** (`[data-vp-nav-toggle]`, cabecera y barra del modo diseño) muestra/oculta la columna de miniaturas; oculta queda una pestaña vertical «LÁMINAS».
- **Salir con cambios**: en el modo diseño y en el editor de marcas, los enlaces abren `confirmarModal` con tres salidas (Guardar y salir / Salir sin guardar / Seguir editando); `confirmarModal` acepta `extra` (tercer botón → `'extra'`), `tono` (`peligro|aviso|info`) y `cancelar`. El aviso nativo solo queda al cerrar la pestaña o recargar.

## Tablas desde SheetFlow
`vplan_tablas.sheetflow_id` enlaza una tabla «otro» con su hoja de SheetFlow: columnas y renglones se leen en vivo y las ediciones desde la lámina se escriben en la hoja (máx. 8 columnas). Ver `docs/sheetflow.md`.

## Pendiente
- Varios planos (con marcas propias) en una lámina; hoy hay un plano por lámina.
- Recovery por bloque (hoy por lámina).
- Deshacer/rehacer en el modo diseño.
