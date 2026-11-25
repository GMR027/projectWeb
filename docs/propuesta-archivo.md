# Rediseño — Archivo de obra

Rediseño del explorador de documentos `/archivos`: carpetas, archivos, búsqueda,
selección múltiple, arrastrar y soltar y panel de detalles. La propuesta visual se generó
en Google Stitch (proyecto **ProjectWeb · Archivo y documentos**,
`projects/4109546143939508527`) con el sistema Vanguard CivilTech y se implementó con las
variables del tema de la aplicación, sin pegar el HTML de Stitch.

## Objetivo

Convertir la tabla del archivero en un **explorador documental de obra** legible con muchos
archivos: encontrar un documento, entender su contexto, moverlo de carpeta y abrir sus
detalles sin salir de la página.

## Decisiones de diseño

- **Hero compacto** con proyecto, documentos, carpetas, categorías, pendientes y un medidor
  del espacio de Archivo frente al límite del proyecto.
- **Rail de carpetas** con «Todos los archivos», «Sin carpeta», «Recientes» y cada carpeta con
  conteo. Acciones de renombrar/eliminar y «Nueva carpeta».
- **Recientes sin cambios de esquema:** muestra los últimos 30 archivos subidos (por `id`) y los
  ordena por subida. No se añade una fecha de subida nueva.
- **Barra de mando** con búsqueda instantánea, categoría, orden y conmutador **Cuadrícula ·
  Lista** persistido en `localStorage`.
- **Dos estados de contenido:** la raíz muestra tarjetas de carpetas y archivos; al abrir una
  carpeta se muestra su cabecera y su lista. La búsqueda oculta las carpetas para centrarse en
  los resultados.
- **Tarjetas de archivo** con icono por tipo, extensión, nombre, archivo original, categoría,
  revisión, fecha, tamaño, autor, carpeta y ensamblado. Las acciones respetan los permisos.
- **Panel de detalles** sin recargar: miniatura de imágenes, metadatos, otras revisiones del
  mismo documento, abrir en visor, descargar, editar, eliminar y mover a carpeta.
- **Selección múltiple** con barra flotante para mover. Las casillas usan `form="arch-mover"`,
  de modo que el formulario funciona incluso antes de que el JavaScript actualice la barra.
- **Arrastrar y soltar** una tarjeta o la selección sobre una carpeta. Como alternativa
  accesible siempre está la barra de selección.
- **Móvil**: KPI en tira horizontal, acciones del hero en una fila, carpetas en dos columnas,
  tarjetas apiladas, panel de detalles como hoja inferior y botón flotante de subida.
- **Sin cambios de rutas, permisos, esquema ni APIs.** Se conservan las rutas de subida, lote,
  categorías, carpetas, mover, descargar, visor, editar y eliminar.

## Archivos principales

| Archivo | Cambio |
|---|---|
| `controllers/ArchivoController.php` | Carga única de archivos, estadísticas de carpetas, filtros, orden y vista Recientes |
| `views/archivos/index.php` + `_hero.php`, `_barra.php`, `_rail.php`, `_carpetas.php`, `_contexto.php`, `_lista.php`, `_detalle.php`, `_modales.php`, `_scripts.php` | Nueva estructura del explorador |
| `src/scss/paginas/_archivero.scss` | Lenguaje visual completo y responsive |
| `src/scss/paginas/_documentos.scss` | Se retiraron las reglas del archivero antiguo; el visor conserva las suyas |
| `public/vendor/archivos.js` | Búsqueda, vista, selección, mover, arrastrar y soltar, detalles y modales |
| `includes/funciones.php` | `archivo_clave_documento()` e `iniciales_nombre()` |
| `views/manual/_secciones.php` | Guía actualizada de Archivo |
| `tests/run.php` | Comprobaciones de los nuevos parciales y del explorador |

## Cómo probar

```bash
npx gulp
php tests/run.php
cd public && php -S localhost:8000 router.php
```

Abrir un proyecto con varios archivos y carpetas y probar:

- Búsqueda por nombre, categoría, autor y revisión.
- Cambio cuadrícula/lista y persistencia al recargar.
- Filtro por categoría, orden y vistas «Todos», «Sin carpeta» y «Recientes».
- Crear, renombrar y eliminar una carpeta.
- Selección múltiple y barra «Mover»; arrastrar una tarjeta a una carpeta.
- Panel de detalles: imagen, metadatos, otras revisiones y acciones según el rol.
- Tema claro/oscuro, escritorio y móvil, con admin, cliente, colaborador y visitante.
