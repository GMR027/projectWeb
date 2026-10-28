# Materiales en el plano de vPlan

_Se lee solo al trabajar en este módulo._

## Qué es
La pestaña **Materiales** del editor de marcas de vPlan (`/vplan/editor?id=…`) permite dibujar **líneas y formas** sobre el plano y relacionarlas con **varias actividades y subactividades** del programa. Con ellas se crea **una tabla de instalación de materiales por lámina**, guardada en SheetFlow, que se imprime como un bloque más de la lámina.

- Herramientas: `controllers/VplanMaterialesController.php`, lógica `classes/MaterialesLamina.php`, JS `public/vendor/materiales-vplan.js` (sin build).
- Vistas: `views/vplan/_materiales-barra.php`, `_materiales-panel.php`, `_materiales-props.php`, `_materiales-modal.php` (se incluyen desde `_editor-lienzo.php`).
- Migración: `database/migracion_materiales_vplan.sql` (`vplan_laminas.materiales_tabla_id` y el tipo `materiales` de `vplan_tablas`).

## Datos
Cada marca guarda en su JSON (`vplan_laminas.marcas_json`):

```json
"material": {
  "items": [
    { "tipo": "actividad", "id": 123 },
    { "tipo": "subactividad", "id": 456, "actividad_id": 123 }
  ]
}
```

- `mat_mostrar` (bool), `mat_tam` (px, 24–400) y `mat_pos` (`centro|borde`) controlan la galería de fotos.
- `mat_colorEstado` (bool): si está activo, la marca toma el color del estado del programa. **Por defecto manda el color elegido por el usuario**, porque las marcas de material ya no usan el color automático de la liga simple (`actividad_id`).
- Las marcas antiguas con `actividad_id` se leen como una asignación de material sin migrar datos.

## Tabla de SheetFlow
- Una por lámina; `vplan_laminas.materiales_tabla_id` apunta a un `VplanTabla` de tipo `materiales`, que a su vez tiene `sheetflow_id`.
- Columnas base (`Sheetflow::ROLES_MATERIALES`): **Material** (`material`), **Fecha de material** (`fecha_material`, fecha fin de la actividad/subactividad) y **Status** (`status_material`, lista No iniciada / En proceso / Completada).
- `datos.meta.materiales.ids` guarda la clave de cada renglón (`a123` actividad, `s456` subactividad). La clave sobrevive a reordenar y a insertar columnas.
- `MaterialesLamina::fusionar()` agrega una fila por clave nueva, **no toca las columnas extra** y conserva los renglones cuya actividad se eliminó (quedan como «sin ubicación»). Con `refrescar=1` actualiza las tres columnas base.
- `Sheetflow::guardarDatos()` usa `sinVinculoSubactividades()`: una hoja normal conserva el vínculo de materiales. `sinVinculo()` (copias, exportación e importación) quita los dos vínculos.

## Editor de marcas
- La pestaña **Materiales** solo aparece si `$config['materiales']` está presente (`VplanController::editor`). Al dibujar desde esa pestaña, la marca nace con `material.items = []`.
- **Propiedades → Materiales de esta marca**: chips de actividades/subactividades, buscador por partida, casilla de fotos, deslizador de tamaño (en vivo) y posición centro/borde.
- **Fotos**: se resuelven del catálogo (`materiales.imagen` de los materiales de la actividad) con `POST /api/vplan/materiales/imagenes`. Se pintan en una capa Konva propia (`matLayer`) para no alterar el tamaño de la marca ni el transformer; el botón redondo con la primera foto solo se ve al editar y no sale en PNG/PDF.
- En líneas la galería va **arriba del trazo, centrada**; en formas, **al centro** o **sobre el borde superior**. El botón se coloca en la esquina superior derecha.
- **Panel Materiales**: resumen, leyenda con colores y conteo de marcas, tabla completa con todas las columnas de SheetFlow, búsqueda y clic en una fila para seleccionar sus marcas en el plano.
- Al guardar una foto o un cambio de asignación se llama a `cargarImagenes()` y `vplan:render` refresca el panel.

## Lámina y PDF
- El bloque de tabla es de tipo `materiales`; `_tabla-adicional.php` lo trata como tabla genérica (`otro`) y muestra «Marcar materiales» y «Editar en SheetFlow».
- Las tablas de vPlan muestran hasta 8 columnas por defecto; **materiales** sube el tope a 30 (`VplanTabla::maxColumnas()`), pero conviene mover/ampliar el bloque o reducir la letra si hay muchas.
- La celda del material lleva una **barra del color** de la marca (`MaterialesLamina::coloresDeMarcas()` en `Vplan::datos`), para reconocerla también en la lámina y en el PDF.
- Al duplicar una lámina **no** se copia su tabla de materiales (la lámina nueva no tiene marcas). Al borrar la lámina se borra su tabla y se conserva la hoja de SheetFlow.

## Rutas
- `POST /api/vplan/materiales/crear`
- `POST /api/vplan/materiales/sincronizar` (`refrescar=1` opcional)
- `POST /api/vplan/materiales/datos`
- `POST /api/vplan/materiales/imagenes` (`actividades[]`)
- `POST /api/vplan/materiales/desvincular`
- `POST /api/vplan/materiales/vincular` (`tabla`)

## Pendiente
- Elegir qué columnas se imprimen en la lámina (hoy se imprimen todas las que caben).
- Numerar los materiales en el plano (M-01, M-02…).
- Longitudes/áreas automáticas con la escala y consolidado de todo el paquete.
