# Rediseño — Administración de obra

Rediseño visual de las herramientas de administración del proyecto: **Directorio, Contratos,
Subcontratos, Cierre de obra, Costos, Catálogo de conceptos, Órdenes de cambio, Riesgos,
Recursos y cuadrillas, Equipo y maquinaria, Datos de obra, Historial de cambios y Acciones
rápidas**.

Las pantallas se generaron en Google Stitch con la clave de `STITCH_API_KEY` del archivo
`.env` y el sistema **Vanguard CivilTech**. El proyecto de Stitch es
`projects/12374215562357501557` (**ProjectWeb · Administración de obra**) y sus pantallas
quedaron exportadas en `design/administracion_obra/` (`code.html` y `screen.png`).

## Objetivo

Que las herramientas administrativas se lean como un **centro de control de obra** y no como
formularios sueltos: qué requiere atención, cuánto dinero o tiempo está en juego, quién
responde y cuál es el siguiente paso, sin perder la densidad de datos que necesita un
residente o un administrador de obra.

## Decisiones de diseño

- **Cabecera de mando**: icono en placa, eyebrow «Administración de obra», chips de contexto
  (personas, vencimientos, montos, pendientes) y acciones a la derecha.
- **KPIs accionables**: cada tarjeta resume un indicador con icono, valor monoespaciado y una
  pista de lectura; los tonos ámbar/rojo marcan lo que exige acción.
- **Pestañas de herramienta** en una barra segmentada, con contadores cuando la vista los tiene.
- **Tablas operativas**: cabecera fija, cifras tabulares, hover con tinte del acento, acciones
  agrupadas a la derecha y columnas numéricas alineadas.
- **Formularios por secciones** (`co-form` / `co-campos`) con foco visible, fondo elevado y pie
  de acciones; dentro de una tarjeta ya existente no repiten el panel.
- **Paneles y divisiones** (`adm-split`, `adm-panel`, `adm-stack`) para finanzas, cumplimiento,
  riesgos y recursos.
- **Estados vacíos y avisos** con la misma jerarquía visual que el resto de la aplicación.
- **Tema claro/oscuro**: todo sale de las variables del proyecto (`--surface`, `--primary`,
  `--danger`, etc.); no se fijan colores de fondo ni textos del tema oscuro.
- **Móvil**: cabecera y KPIs en dos columnas, tabs con desplazamiento horizontal, tablas que se
  desplazan dentro de su panel y acciones táctiles.
- **Accesibilidad**: foco visible en campos y acciones, `aria-current` en pestañas, iconos con
  `title`/`aria-label` y `prefers-reduced-motion`.

## Cambios principales

| Archivo | Cambio |
|---|---|
| `src/scss/paginas/_administracion.scss` | Nueva capa visual completa de las 13 herramientas |
| `src/scss/app.scss` | Importa la hoja para las vistas que usan `app.css` completo |
| `includes/funciones.php` | `css_paginas_de_vista()` carga `administracion` en las 13 vistas |
| `views/directorio/index.php` y `controllers/DirectorioController.php` | Hero con chips, KPIs de los cinco catálogos y conteos globales |
| `views/contratos/index.php` | Hero con vencimientos y KPI de registros |
| `views/subcontratos/index.php` | Hero con documentos faltantes y KPIs de monto/saldo |
| `views/cierre/index.php` | Hero con avance y KPIs de completados, proceso y pendientes |
| `views/costos/index.php` | Hero financiero e iconos en los nueve KPIs |
| `views/conceptos/index.php` | Hero con conceptos, directo y partidas |
| `views/cambios/index.php` | Hero con costo autorizado, solicitudes y retiros |
| `views/riesgos/index.php` | Hero con severidades y iconos en la matriz/KPIs |
| `views/recursos/index.php` | Hero y KPIs de recursos, asignaciones, sobrecargas y periodo |
| `views/equipo/index.php` | Hero con equipos, mantenimiento y horas del mes |
| `views/datos-obra/index.php` | Hero y KPIs de fechas, responsables, conceptos y alertas |
| `views/historial/index.php` | Hero y KPIs de pendientes, aceptados y revertidos |
| `views/acciones/index.php` | Hero y KPIs de frentes y pendientes |
| `design/administracion_obra/` | Exportación de las cinco pantallas de referencia de Stitch |
| `docs/rediseno-administracion.md` | Este documento |

**Sin cambios de rutas, permisos, esquema, IDs, formularios ni APIs.** Las acciones siguen
apuntando a los mismos endpoints y los JavaScript existentes conservan sus selectores.

## Pantallas de referencia

| Pantalla | Ruta en Stitch | En el repositorio |
|---|---|---|
| Directorio | `projects/12374215562357501557/screens/0b9d20d844624f42a33431935edf6cd6` | `design/administracion_obra/directorio/` |
| Contratos, fianzas y seguros | `.../screens/548486eea8f644ae8990b94e6137c73a` | `design/administracion_obra/contratos/` |
| Costos y presupuesto | `.../screens/082474a321dd45de8e909498919570cc` | `design/administracion_obra/costos/` |
| Riesgos y recursos | ver `screen.png` | `design/administracion_obra/riesgos/` |
| Historial y datos de obra | ver `screen.png` | `design/administracion_obra/historial/` |

## Cómo probar

```bash
npx gulp
php tests/run.php
cd public && php -S localhost:8000 router.php
```

Abrir un proyecto con datos y recorrer las 13 herramientas:

- Revisar la cabecera, los chips de contexto y los KPIs en escritorio y móvil.
- Cambiar de pestaña en Directorio, Costos, Recursos e Historial.
- Crear/editar un registro y comprobar que los formularios y sus acciones siguen funcionando.
- Alternar tema claro/oscuro y revisar contraste de tablas, badges y estados.
- Confirmar que las tablas anchas se desplazan dentro de su panel y que las acciones no se
  salen de la tarjeta.

## Notas

- Los conteos del Directorio se calculan en el controlador para que el hero y los KPIs no
  dependan de la pestaña activa.
- La hoja `administracion.css` se carga al final de las hojas propias de cada herramienta,
  igual que `ventanas.css`, para poder afinar tablas y formularios sin tocar los estilos base.
- No se añadieron librerías, CDN ni fuentes nuevas.
