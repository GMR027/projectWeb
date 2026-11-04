# Importar Primavera P6 — `/programa/importar/p6`

Trae el programa de P6 sin copiar a mano. Acepta los dos formatos habituales:

- **XML de P6** (`APIBusinessObjects`).
- **XER** (texto por tablas: `PROJECT`, `WBS`, `TASK`, `TASKPRED`, `CALENDAR`).

## Qué se importa

| P6 | Proyecto |
|---|---|
| WBS | Partidas (código = `wbs_short_name`, nombre = `wbs_name`) |
| Activity | Actividades (código `Id`/`task_code`, fechas, % avance, prioridad, hitos) |
| Relationship | `actividad_dependencias` con tipo FS/SS/FF/SF y espera en días |
| Calendar | Horas por día para convertir duraciones; el calendario real se recalcula con el del proyecto |

## Cómo funciona

`Classes\P6Importador`:

- `leer($ruta)` — lectura pura (XML o XER), sin base de datos; la usan las pruebas.
- `analizar($ruta, $pid, $opciones)` — valida, cruza con lo existente, arma la vista previa y guarda el token en `storage/import/<token>/p6.json`.
- `aplicar($token, $proyecto)` — en una transacción crea/reusa partidas, crea o actualiza actividades e inserta dependencias con `INSERT IGNORE`; recalcula las partidas.
- `limpiar($token)` — borra la vista previa temporal.

Opciones:

- **Fechas**: recalcular con el calendario del proyecto (por defecto) o conservar las del archivo.
- **Código repetido**: actualizar (por defecto), saltar o crear con código nuevo.

## Seguridad y límites

- Máximo 30 MB (`P6Importador::MAX_BYTES`).
- XML sin `DOCTYPE` ni `ENTITY` (sin entidades externas), `LIBXML_NONET`.
- Nada se escribe hasta confirmar; la vista previa solo guarda un JSON temporal.
- Se omiten tareas de resumen WBS y relaciones con actividades inexistentes (con aviso).

## Rutas

- `GET/POST /programa/importar/p6`
- `POST /programa/importar/p6/confirmar`
- `POST /programa/importar/p6/cancelar`

Vista: `views/programa/importar-p6.php`. Pruebas: `tests/fixtures/p6-demo.xml` y `p6-demo.xer` en `tests/run.php`.
