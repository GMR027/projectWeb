# Dependencias de materiales

_Se lee solo al tocar `classes/DependenciasMateriales.php`, `/materiales/dependencias` o el Programador._

## Qué resuelve

Un material puede necesitar que **otros materiales** estén disponibles antes de usarse o instalarse.
La relación es **muchos a muchos**: un material puede requerir varios y ser requerido por varios.

Ejemplos:

```
Caja ─┐
Base ─┼──► Cámara bullet
Cámara┘

Soportería → Cable
Muro → Pintura
```

Se combina con `actividad_materiales`:

- `actividad_materiales` = qué materiales necesita cada actividad y su **rol** (`instala` o `consume`).
- `material_dependencias` = qué material requiere que otros estén **entregados** o **instalados**.

## Modelo

- `proyectos.modo_dependencias_materiales`: `apagado | informativo | automatico`.
- `material_dependencias`: `proyecto_id`, `predecesor_id`, `sucesor_id`, `requisito`
  (`entrega | instalacion`), `retraso` (días hábiles) y `nota`.
- `material_dependencias_cambios`: foto de fechas para **deshacer** una reprogramación.
- `actividad_materiales.rol`: `instala` (por defecto) o `consume`.
- Migración: `database/migracion_dependencias_materiales.sql`. En el clúster,
  `database/aplicar_migraciones_cluster.sh` agrega las columnas con `col` y aplica
  `database/migracion_dependencias_materiales_tablas.sql` (solo tablas, idempotente).

## Cálculo

`DependenciasMateriales::calcular()` es puro y tiene pruebas en `tests/run.php`:

```
disponibilidad(material, entrega) =
    max(entrega propia, disponibilidad(prerrequisito, su requisito) + retraso)

disponibilidad(material, instalación) =
    max(instalación propia, disponibilidad(material, entrega))
```

- La **instalación** de un material es la primera actividad que lo instala (`MIN(fecha_fin)` con
  `rol = instala`); si no hay ninguna que lo instale, se usa la primera que lo consume y, si tampoco,
  su entrega.
- Si la **misma actividad** instala el material prerrequisito y el dependiente, el enlace de
  instalación no se puede resolver dentro de ella: la actividad se marca como bloqueada y se pide
  cambiar el requisito a «entregado» o separar la instalación.
- Si un prerrequisito no tiene fecha, el material queda **bloqueado** y no se inventa una fecha.
- Una actividad puede empezar cuando **todos** sus materiales tienen disponibilidad. El piso es el
  máximo de ellos; si alguno está bloqueado, la actividad se reporta como bloqueada.
- Los ciclos se detectan con Kahn/DFS y se rechazan al guardar.

## Pantalla

`/materiales/dependencias` (permiso heredado de `/materiales`, pestaña dentro del Catálogo):

- Modos apagado/informativo/automático.
- KPIs: relaciones, materiales bloqueados, actividades bloqueadas, actividades atrasadas y días.
- Vista **Cadenas** (tablero por niveles con líneas SVG) y vista **Lista**.
- Panel del material: requiere, requerido por, actividades que lo usan, disponible desde, se necesita el.
- Modal de conexión con **selección múltiple** de prerrequisitos y requisito/retraso por cada uno.
- **Revisar y reprogramar** (vista previa, aplicar, deshacer).
- **Sugerir actividades**: relaciones nuevas entre actividades existentes y actividades nuevas para
  materiales que participan en una cadena y todavía no tienen actividad. Las cadenas de materiales
  nuevos se crean en orden topológico y cada actividad espera a sus prerrequisitos. Nada se guarda
  sin aceptar.

Archivos: `views/materiales/dependencias.php`, `public/vendor/dependencias-materiales.js`,
`src/scss/paginas/_dependencias-materiales.scss`, `controllers/MaterialDependenciasController.php`.

## Integraciones

- **Programador IA** (`classes/Programador.php`): agrega `disp` y `req` a cada material, usa la
  disponibilidad efectiva como piso de fecha y genera obstáculos de material.
- **Cruce de materiales** (`classes/CruceMateriales.php`): estado `bloqueado_requisito`.
- **Inconsistencias**: reglas `mat_requisito_sin_fecha` y `mat_requisito_tarde`.
- **Salud** y **Acciones rápidas**: actividades bloqueadas o atrasadas por materiales.
- **Propagación automática** tras cambiar fechas de actividades o marcar/desmarcar una entrega.
- **Plantillas de proyecto**: copia `material_dependencias` y el rol de `actividad_materiales`.
- **Respaldo**: la tabla se descubre sola por su FK a `proyectos`.

## Permisos

La ruta vive bajo `/materiales`, así que el visitante la ve si tiene habilitado el Catálogo de
materiales. Editar exige `puede_gestionar_proyecto()`.
