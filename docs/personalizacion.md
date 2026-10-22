# Personalización (Fase 4)

Migraciones: `database/migracion_personalizacion.sql` (tablas) y `database/migracion_personalizacion_columnas.sql` (columnas de `proyectos` y `usuarios`). Ambas están en `schema.sql` y en `aplicar_migraciones_cluster.sh`.

| # | Qué | Dónde |
|---|---|---|
| 4.1 | Diseño de columnas de Lookahead, Punch, Submittals, Bitácora y Reporte diario | `classes/ListaDiseno.php`, `views/listas/diseno.php`, `views/listas/_barra.php`, ruta `/lista/diseno` |
| 4.2 | Campos personalizados (filtro y agrupación) | `classes/CamposPersonalizados.php`, `views/campos/index.php`, `/campos`, `/api/campos/valor`, `src/js/campos.js` |
| 4.3 | Plantillas de proyecto completas | `classes/PlantillaProyecto.php`, `PlantillasProyectoController`, `/plantillas-proyecto`, select en `proyectos/crear` |
| 4.4 | Marca del cliente en los PDF | `classes/Marca.php`, `MarcaController`, `/marca`, opción `'marca' => $proyecto` de `Pdf::generar/preparar` |
| 4.5 | Inicio personalizable por usuario | `classes/InicioUsuario.php`, `DashboardController`, `views/dashboard/index.php`, `usuarios.inicio_json` |
Controlador de 4.1 y 4.2: `controllers/PersonalizacionController.php`.

## 4.1 Diseño de listas
- `ListaDiseno::HERRAMIENTAS` es el catálogo: `herramienta => [título, entidad de campos, ruta, ¿ordenable?, obligatorias, clave => [nombre, ¿se ve en móvil?]]`. Se guarda en `disenos_lista(proyecto_id, herramienta, columnas_json [{k,n}])`; sin fila = clásico + los campos personalizados de la herramienta (antes de «acciones»).
- Las vistas hacen `foreach ($diseno['cols'] as $c) switch ($c['k'])` y un `default:` que pinta el campo personalizado con `CamposPersonalizados::celda()`. **Para agregar una columna a una lista:** súmala al catálogo y a su `switch`.
- Lookahead no es ordenable: su orden va ligado al encabezado de semanas; solo muestra/oculta/renombra ID, Actividad, % Comp, PTA y Limitaciones. Sus campos personalizados solo filtran. Punch pinta cada pendiente como tarjeta: el diseño elige y ordena los datos de la línea.
- El programa conserva su propio diseño (`ProgramaColumnas`, `/programa/diseno`); admite claves `cp_<id>` además de las suyas.

## 4.2 Campos personalizados
- `campos_personalizados(proyecto_id, entidad actividad|punch|submittal|bitacora|reporte, nombre, tipo texto|numero|fecha|lista, opciones, orden)` y `campos_valores(campo_id, registro_id, valor)`. Máx. 12 por herramienta. Los valores huérfanos (registro borrado) no estorban: solo se leen por registro.
- En un diseño la columna es `cp_<id>`. Al borrar un campo se quita de todos los diseños (`ListaDiseno::quitarClave`).
- Filtro por URL: `cp[<id>]=valor` (`__vacio` = sin valor); agrupar: `agrupar=<id>`. `CamposPersonalizados::aplicar()` devuelve los registros filtrados y ordenados por grupo; al agrupar se desactiva el paginador (`pag_attr`). El programa y el Lookahead solo filtran (`coincide()`).
- Editar en la celda: `.cp-input` → POST `/api/campos/valor` (`proyecto_api('gestionar')`, comprueba que el registro sea del proyecto).
- `RespaldoProyecto` los respalda solos (llaves foráneas).

## 4.3 Plantillas de proyecto
- No confundir con `Classes\Plantillas` (tipo de obra). Se guardan en `plantillas_proyecto.datos` (JSON, versión 1) con `usuario_id`; `compartida = 1` (solo la pone el admin) las muestra a todo el que crea proyectos.
- Entra: configuración del proyecto (`CAMPOS_PROYECTO`), jornada, partidas, actividades (fechas como **días desde el inicio del proyecto**; el fin se recalcula con la jornada del proyecto nuevo; avance 0 / no iniciada / PTA 0), dependencias, campos personalizados y diseños (las claves `cp_` se reasignan), categorías y catálogos, tipos de permiso, estampas, config del Lookahead, tipo de obra y materiales (sin compras, fechas ni archivos).
- No entra: avance, reportes, bitácora, minutas, fotos, archivos, costos, RFI, restricciones, festivos (son de fechas concretas), submittals, MSR, líneas base.
- `aplicar()` solo a un proyecto **vacío** (sin partidas), en una transacción. Se usa al crear (`plantilla_id`) y desde `/plantillas-proyecto?proyecto=`.

## 4.4 Marca
- `proyectos.marca_activa/nombre/logo/color/color2`. `Pdf::preparar` recibe `'marca' => Proyecto` y llama a `Marca::aplicar()` (banda con logo + CSS de títulos y `th`). Lo piden: Pdf (programa), Lookahead, Last Planner, Curva, MSR, Submittal, Minuta, Reportes, Punch, Informes y Seguridad. **No** lo piden los documentos que el usuario diseña (Redactor, SheetFlow, Editor, láminas del vPlan, Ensamblador).
- El logo se sube con `guardar_imagen_subida('marca_logo', 'logos')`; el respaldo del proyecto lo recoge por el nombre del archivo.

## 4.5 Inicio
- `usuarios.inicio_json = {bloques:[{k,on}], proyectos_n}`. Bloques en `InicioUsuario::BLOQUES`; sin configuración = resumen + proyectos. Para un bloque nuevo: constante + `case` en `views/dashboard/index.php` (+ datos en `InicioUsuario::datos()` si necesita consultas).

## Pruebas
Con copia de BD (AGENTS.md, punto 8) y `php tests/run.php` para las piezas sin BD.
