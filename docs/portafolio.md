# Tablero de portafolio — `/portafolio`

Vista de dirección que compara todas las obras visibles para el usuario. **No modifica datos**: lee lo que ya calculan la Curva S, Costos, Salud y el programa.

## Quién entra

- **admin**: todas las obras.
- **cliente**: las que creó y las que el administrador le asignó (`Proyecto::visiblesPara`).
- **visitante**: se redirige a `/dashboard`.

Ruta en `RUTAS_GENERALES`; el controlador refuerza el rol. Enlaces en el menú lateral y en el listado de proyectos.

## Indicadores (`Classes\Portafolio`)

| Indicador | Fuente |
|---|---|
| Avance real / planificado | `CurvaS::calcular()` |
| SPI | `CurvaS::calcular()['resumen']['spi']` |
| CPI | `Costos::resumen()['total']['cpi']` (si hay presupuesto) |
| PPC | Actividades que debían terminar en la última semana completa y están al 100 % (o con fin real dentro de la semana) |
| Atrasadas / Punch | Conteos del programa y del punch |
| Salud | `Salud::semaforo()` |

## Alertas por tendencia

Se generan cuando el indicador **empeora**, no solo cuando está mal:

- SPI < 0.95 y baja ≥ 0.05 frente a hace 4 semanas.
- CPI < 1 y baja ≥ 0.05 frente a hace 4 semanas.
- PPC < 70 % y baja ≥ 10 puntos frente al promedio de las 3 semanas anteriores.
- Salud en rojo.

Se guardan en `portafolio_alertas` (una fila por obra y clave) para que la **campana de notificaciones** las lea sin recalcular. Se refrescan al abrir `/portafolio` y con:

```
php bin/portafolio_alertas.php
```

## Exportación

`GET /portafolio/exportar?formato=pdf|ods` con los mismos filtros de la URL:

- `Classes\Exportadores\PortafolioPdfExporter` (horizontal).
- `Classes\Exportadores\PortafolioSheetExporter` (.ods editable en Excel).

El archivo se genera en `public/exportaciones/` y se borra tras la descarga.

## Migración

`database/migracion_portafolio.sql` (`portafolio_alertas`).
