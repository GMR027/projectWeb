# Administración de obra: Calidad, Equipo, Contratos y Cierre

Cuatro herramientas para lo que pasa después de programar: controlar la calidad, el equipo, los documentos con vencimiento y la entrega final. Están en el menú del proyecto:

| Herramienta | Ruta | Menú | Quién captura |
|---|---|---|---|
| Calidad e inspecciones (ITP) | `/calidad` | Campo | admin y cliente dueño |
| Equipo y maquinaria | `/equipo` | Campo | admin y cliente dueño |
| Contratos, fianzas y seguros | `/contratos` | Administración | admin y cliente dueño |
| Cierre de obra | `/cierre` | Administración | admin y cliente dueño |

El visitante puede consultarlas si se le habilitan en **Usuarios y accesos**, salvo Contratos y Cierre, que el menú oculta a los visitantes.

## Instalación

```bash
mysql -u root project < database/migracion_calidad_equipo_contratos_cierre.sql
mysql -u root project < database/migracion_ia_rendimiento.sql
```

Las instalaciones nuevas ya traen las tablas en `database/schema.sql`. En el clúster, `database/aplicar_migraciones_cluster.sh` la aplica sola.

El **proyecto DEMO** trae ejemplos claros: 6 planes de calidad (uno por cada estado, con una no conformidad abierta), 5 equipos con horas, costos y servicios próximos, 7 contratos/fianzas/seguros que cubren las cuatro situaciones y la lista de cierre con 10 puntos (30 % de avance). Al entrar como demo, cada herramienta muestra un aviso con lo que estás viendo. Se recrean con `php database/seed_demo_herramientas.php`.

## Calidad (`Classes\Calidad`)

- `calidad_planes`: plan de inspección/prueba por actividad o de todo el proyecto, con norma, responsable, fecha y estado.
- `calidad_puntos`: los puntos a revisar. Se capturan uno por línea al crear el plan (`descripción | criterio`) y luego se marcan **cumple / no cumple / no aplica / pendiente**, con observación y «resuelta».
- El estado del plan se recalcula solo: una no conformidad abierta lo deja **no conforme**.
- Resumen: planes, puntos revisados y no conformidades abiertas.
- **Plan de inspección con IA:** en Herramientas IA → «Plan de inspección (ITP) con IA» (`Classes\Herramientas\ItpCalidad`) se eligen las actividades pendientes; la herramienta cruza la normativa ya vinculada y propone los puntos. Al aprobar el informe se crean el plan y sus puntos aquí, listos para marcar en campo. No crea nada mientras es borrador.

## Equipo (`Classes\Equipo`)

- `equipo`: nombre, tipo, marca/modelo, serie, propietario, tarifa por hora, estado y próximo servicio (por fecha o por horas).
- `equipo_horas`: horas por día y actividad, operador e importe calculado con la tarifa.
- Avisos de servicios próximos (fecha dentro de 15 días u horas acumuladas cerca del límite) en la parte superior de la herramienta.
- Resumen del mes: horas y costo.

## Contratos (`Classes\Contratos`)

- `contratos`: tipo (contrato, fianza, seguro, licencia), número, contraparte, objeto, monto/moneda, inicio, vencimiento, días de aviso, estado y documento del Archivo.
- La situación **vigente / por vencer / vencido / cerrado** se calcula con la fecha; no se guarda.
- Filtros por situación y resumen de montos vigentes.

## Cierre (`Classes\Cierre`)

- `cierre_obra`: fecha de entrega, responsable y notas del acta.
- `cierre_items`: lista de entrega por categoría (as-built, garantías, manuales, punch, documentos, otros), con responsable, fecha compromiso, estado y documento del Archivo.
- **Agregar la lista estándar** crea 10 puntos típicos si el proyecto aún no tiene.
- **Acta PDF** (`/cierre/pdf`) genera el acta de entrega-recepción con el avance, la tabla por categoría y las firmas.

## Permisos y eventos

- Las escrituras usan `proyecto_de_peticion(true)` (admin o cliente dueño).
- Las cuatro claves están en `Model\Usuario::HERRAMIENTAS` y en el menú (`views/templates/herramientas-proyecto.php`), así que el visitante solo las ve si se le habilitan.
- **Resumen matutino:** si el proyecto tiene activado el resumen de la obra (Integraciones → Telegram), `Classes\ResumenObra` incluye los contratos/fianzas por vencer y los equipos con servicio próximo. Dentro de cada herramienta también se ven siempre.
