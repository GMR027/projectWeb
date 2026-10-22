# Notificaciones y novedades de la plataforma

Campana de la barra superior (`views/templates/topbar.php`) y sección **Ajustes → Novedades**
(`views/ajustes/index.php`), más la página `/novedades`.

## Qué muestra la campana

Se calcula en el propio `topbar.php` en cada página:

| Bloque | De dónde sale |
|---|---|
| **Novedades de la plataforma** | `Classes\Novedades::para($rol, $usuarioId)` — las 3 últimas; enlazan a `/novedades#ancla` |
| **Actividades de hoy** | Sección `hoy` de `Classes\AccionesRapidas::secciones()`, la misma que pinta el sidebar |
| **Acciones rápidas** | Resto de secciones de `AccionesRapidas` con `total > 0` (título y cantidad; enlazan a `/acciones#acc-…`) |

El sidebar calcula `$accionesRapidas` con `puede_gestionar_proyecto()`, así que **el cliente no
recibe las secciones del administrador** (materiales pendientes, actividades sin materiales,
materiales sin asignar) y el visitante solo ve lo que su herramienta permita. El topbar reutiliza
esa variable: no vuelve a consultar la base.

El contador de la campana es **novedades sin ver + actividades de hoy** (máximo visual `9+`).
Los pendientes de Acciones rápidas se ven con su cantidad dentro del panel, para que el número de
la campana no quede fijo en `9+` en proyectos con mucho pendiente.

## Publicar una novedad

No hay tabla: el historial vive en `classes/Novedades.php` (`ENTRADAS`), la más reciente primero.

```php
[
    'fecha'  => '2026-10-21 17:30',   // Y-m-d H:i — la hora decide qué está «sin ver»
    'tipo'   => 'nuevo',              // nuevo | mejora | arreglo
    'titulo' => '…',
    'texto'  => '…',
    'roles'  => ['admin'],            // opcional; sin esto la ven admin, cliente y visitante
]
```

`Novedades::tipo()` da etiqueta e icono; `Novedades::ancla()` el id del bloque en `/novedades`.

## Leído / no leído

- Columna `usuarios.novedades_vistas DATETIME NULL` (migración `database/migracion_novedades.sql`,
  `col usuarios novedades_vistas` en `aplicar_migraciones_cluster.sh`).
- Una novedad está «sin ver» si su fecha es mayor que esa marca; `NULL` = nunca abrió (todas nuevas).
- Se marca al **abrir la campana** (fetch a `POST /novedades/vistas`), al **abrir `/novedades`** y con
  el botón **Marcar como vistas** de Ajustes.
- `NovedadesController::vistas()` responde JSON si la petición es `fetch` y, si es un formulario,
  redirige al campo `volver` (solo rutas internas).

## Archivos

| Archivo | Papel |
|---|---|
| `classes/Novedades.php` | Entradas, tipos, filtro por rol y estado leído |
| `controllers/NovedadesController.php` | `/novedades` y `POST /novedades/vistas` |
| `views/templates/topbar.php` | Campana y panel |
| `views/templates/novedades-lista.php` | Lista compartida (página y Ajustes) |
| `views/novedades/index.php` | Historial completo |
| `src/js/notificaciones.js` | Abrir/cerrar el panel y marcar leídas |
| `src/scss/layout/_notificaciones.scss` | Estilos (en móvil el panel se abre como hoja bajo la barra) |

## Al agregar una herramienta nueva

Además de documentarla en el manual, agrega su novedad en `classes/Novedades.php` con la fecha y
hora del despliegue (si no, la campana no la anuncia).

## Prueba rápida

1. `UPDATE usuarios SET novedades_vistas = NULL WHERE id = <id>;` → la campana muestra `9+`.
2. Abrir la campana o `/novedades` → `novedades_vistas` queda con la hora actual y el contador baja
   a las actividades de hoy.
3. Entrar como cliente con un proyecto asignado → no aparecen «Materiales pendientes», «Actividades
   sin materiales» ni «Materiales sin asignar», y las novedades con `roles => ['admin']` no salen.
