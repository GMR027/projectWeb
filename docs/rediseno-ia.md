# Rediseño — Sección de Inteligencia artificial

Rediseño de las cuatro pantallas de IA de ProjectWeb: **Programador de actividades**,
**Inconsistencias**, **Propuestas de IA** y **Revisiones**. La propuesta visual se generó en
Google Stitch y se implementó con las variables del tema de la aplicación, sin pegar el HTML
de Stitch.

## Objetivo

Convertir la sección de IA en una **consola de operaciones** coherente: qué propone la IA, qué
evidencia lo respalda, qué falta aprobar y qué está desactualizado. La IA nunca aplica cambios
sola; la franja de confianza y las acciones de aprobación lo dicen en todas las pantallas.

## Diseño en Stitch

- **Proyecto**: `ProjectWeb · Inteligencia Artificial` (`projects/11602835022219002283`).
- **Design system**: `Vanguard CivilTech` (`assets/502b84523b8d4f268b05e09005686895`),
  creado desde `design/ia/DESIGN.md`.
- **Pantallas de escritorio (1440 px)**:
  - Programador de actividades: `bab82c1610dd4509ae01e9fb2007f2af`
  - Inconsistencias: `a73385ddce4045fe9e8ed36b8965d4af`
  - Propuestas de IA: `7ff82dfd80604868a51360f15ae6c555`
  - Revisiones: `39280040a4214f1b954306ac7c41025f`
- **Pantallas móviles (390 px)**:
  - Programador: `7c85d2516f7e4e75a5624c1b5ddbfcbe`
  - Inconsistencias: `a860eb4216e544eca4ca854ae63856ae`
  - Propuestas: `436b128fe54d4ca0adb463b9b1adde67`
  - Revisiones: `e4ca5c22398449bc98150a01367830a7`

Las pantallas son referencia visual; la implementación usa los tokens reales del tema
(`--primary`, `--secondary`, `--success`, `--warning`, `--danger`, `--surface`, etc.) y
funciona en tema claro y oscuro, con la paleta que tenga cada proyecto.

## Decisiones de diseño

- **Consola de 3 zonas**: rail de contexto (escenarios, análisis, herramientas o dominios),
  lienzo central y un inspector de aprobación/salud/riesgo. En tablet el rail se vuelve
  horizontal y el inspector baja; en móvil todo se apila.
- **Franja de confianza**: *«La IA propone; tú apruebas. Nada se aplica solo.»* con enlace al
  historial de cambios, en las cuatro pantallas.
- **Acento de IA**: cian para lo que propone o detecta la IA; naranja para las acciones del
  usuario; verde/ámbar/rojo solo para estado, severidad y vencimiento.
- **Fichas de hallazgo**: severidad con etiqueta (no solo color), evidencia, actividades
  afectadas, restricciones, solución propuesta y acciones. Los estados aceptada/rechazada se
  atenúan y muestran quién y cuándo.
- **KPIs accionables**: cada tarjeta enlaza al filtro correspondiente. Las cifras usan
  tipografía monoespaciada y se recalculan con los contadores existentes.
- **Sin funcionalidad inventada**: no se añadió un «aceptar todo» que no exista; la aprobación
  sigue siendo por partida, por hallazgo o por lote de bajo riesgo, con las rutas actuales.

## Archivos principales

| Archivo | Cambio |
|---|---|
| `design/ia/DESIGN.md` | Design system de la consola IA (Vanguard CivilTech + reglas de IA) |
| `docs/rediseno-ia.md` | Este documento |
| `src/scss/componentes/_ia.scss` | Nuevo: consola, rail, inspector, fichas, chips, score, trust strip y responsive |
| `src/scss/componentes/_suite.scss` | Las cuatro vistas IA entran en la capa visual compartida |
| `src/scss/app-base.scss`, `src/scss/app.scss` | Importan `_ia.scss` |
| `src/scss/paginas/_programador.scss` | Reglas de la consola del Programador y móvil |
| `src/scss/paginas/_inconsistencias.scss` | Fichas y layout; se conservan las reglas legacy de otras herramientas |
| `src/scss/paginas/_centro-ia.scss` | Bandeja de propuestas y niveles de riesgo |
| `src/scss/paginas/_revisiones.scss` | Resumen, dominios y tablas de revisiones |
| `includes/funciones.php` | Mapeo de `centro-ia` y `revisiones` a sus hojas |
| `views/programador/index.php` | Markup de la consola de escenarios |
| `views/inconsistencias/index.php` | Markup de hallazgos, salud y vigilancia |
| `views/centro-ia/bandeja.php` | Markup de la bandeja de propuestas |
| `views/revisiones/index.php` | Markup del dashboard de revisiones |

## Qué se conserva

- Rutas, permisos, controladores, modelos y esquema de base de datos.
- Todos los formularios y acciones: aceptar/rechazar/reabrir/corregir, guardar análisis,
  eliminar, comparar escenarios, aceptar por lote, niveles de riesgo, marcar revisiones y
  reportes PDF/ODS.
- Los ganchos que usan los JavaScript: `.inc-item`, `.inc-grupo`, `inc-abrir-editor`,
  `inc-act-nombre`, `window.HALLAZGOS`, `#prg-partida`, `#prg-solo`, `.prg-seg`,
  `#prg-comparativo`, `ia-lote-btn`, `data-riesgo`, `data-rev-tabla`, `.rev-chip`, etc.
- La paginación de las listas largas (`pag_attr($proyecto)`) y los contadores en vivo.

## Cómo probar

```bash
npx gulp
cd public && php -S localhost:8000 router.php
php tests/run.php
```

Abrir un proyecto con datos y revisar:

1. **Programador**: escenarios, comparar, guardar/eliminar, aceptar/deshacer por partida,
   filtros del comparativo, «solo las que cambian», curva S y seguimiento de hallazgos.
2. **Inconsistencias**: salud, vigilancia, filtros, lista y «Por problema», corregir en línea,
   aceptar/rechazar/reabrir y reportes.
3. **Propuestas**: filtros por herramienta/riesgo, aprobación por lotes, niveles de riesgo y
   enlaces a la herramienta de origen.
4. **Revisiones**: resumen, dominios, filtros, chip de revisión, historial y última revisión.

Probar tema claro/oscuro, escritorio/tablet/móvil y un proyecto sin análisis para ver los
estados vacíos. Al tocar las hojas compartidas, revisar también Análisis de materiales, Punch,
Seguridad, Calidad y Normativa.
