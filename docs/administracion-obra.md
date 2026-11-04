# Administración de obra: Calidad, Equipo, Contratos y Cierre

Cuatro herramientas para lo que pasa después de programar: controlar la calidad, el equipo, los documentos con vencimiento y la entrega final. Están en el menú del proyecto:

| Herramienta | Ruta | Menú | Quién captura |
|---|---|---|---|
| Calidad e inspecciones (ITP) | `/calidad` | Campo | admin y cliente que gestiona el proyecto |
| Equipo y maquinaria | `/equipo` | Campo | admin y cliente que gestiona el proyecto |
| Contratos, fianzas y seguros | `/contratos` | Administración | admin y cliente que gestiona el proyecto |
| Cierre de obra | `/cierre` | Administración | admin y cliente que gestiona el proyecto |

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

### La inspección detiene el avance (compuerta de calidad)

`calidad_planes.bloquea` (1 por defecto) convierte el plan en un **punto de retención** de su actividad:

| Estado del plan | Efecto sobre `actividades.progreso` |
|---|---|
| Puntos pendientes | tope **99 %**: se puede trabajar, no cerrar |
| No conformidad abierta (`no_cumple` sin resolver) | **congelado** en el avance actual |
| Puntos todos en cumple/NA, o plan `cerrado` | libre |
| `bloquea = 0` | el plan es solo informativo |

- La regla vive en `Classes\Calidad::bloqueo()` y `::tope()`; `bloqueosProyecto()` la calcula para una lista completa en dos consultas.
- Se aplica en **un solo punto**: `Model\Actividad::guardar()` recorta el avance, así que cubre programa, edición inline, parte diario, bot de Telegram, API REST y cualquier herramienta futura. `Actividad::omitirCompuertaCalidad(true)` da el escape controlado a las correcciones de datos (Inconsistencias) y se restaura con `finally`.
- El controlador del programa (`ProgramaController::actualizarProgresoAjax`) hace una comprobación previa para devolver el motivo y el enlace al plan en vez de un recorte silencioso.
- El parte diario **no se pierde**: `ParteDiario::aplicarAvance()` guarda el tope y devuelve el aviso (`ParteDiario::avisos()`), que el controlador muestra al abrir el detalle.
- **Liberaciones** (`calidad_liberaciones`): solo admin o dueño (`Calidad::puedeLiberar()`), con motivo obligatorio (≥10 caracteres) y un tope autorizado. Guardan `nc_snapshot` = no conformidades abiertas al firmar; si aparece una nueva, la liberación **caduca sola** (se marca «Caducada» en la lista). Se pueden revocar. Todo queda en `Auditoria`.
- UI: candado junto al % en el Programa, chip «Calidad» en el parte diario, KPI y tarjeta **Actividades detenidas** con el formulario de liberación en `/calidad`, y la lista de liberaciones firmadas.
- Migración: `database/migracion_calidad_bloquea.sql`.

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

## Riesgos (`Classes\Riesgos`)

- `riesgos`: título, descripción, categoría (técnico, clima, suministro, seguridad, financiero, permisos, mano de obra, calidad, otro), `probabilidad` (1-3), `impacto` (1-3), responsable, estrategia, plan de acción, fecha compromiso, estado (identificado, en tratamiento, materializado, cerrado) y `actividad_id` opcional.
- **La severidad no se guarda:** `Riesgos::severidad()` la calcula (probabilidad × impacto, 1-9) y `::nivel()` la pinta: verde ≤3, amarillo 4-6, rojo ≥7. Cambiar la escala en el futuro no requiere migrar datos.
- **Matriz 3×3** (`Riesgos::matriz()`) cuenta los riesgos abiertos por casilla; la vista permite filtrar por severidad, estado, categoría y texto.
- **Materializar** (`Riesgos::materializar()`): convierte el riesgo en una restricción activa de su actividad, con el tipo de restricción que corresponde a la categoría; guarda `restriccion_id` y cambia el estado a `materializado`. Si el riesgo no tiene actividad, pide ligarla.
- **Salud del proyecto** (`Salud::semaforo()`): indicador «Riesgos altos abiertos». Un rojo con compromiso vencido o de esta semana pone el semáforo en rojo.
- **Programa y Lookahead:** `Salud::riesgosActividades()` suma los riesgos rojos ligados a la actividad a la etiqueta «En riesgo» que ya usan material, punch, RFI y permisos.
- **Resumen matutino de Telegram:** incluye los riesgos altos abiertos (máximo 3 en el texto).
- **PDF** (`/riesgos/pdf`): matriz 3×3, resumen y lista con responsable, compromiso y plan, con la marca del cliente.
- Permisos: admin y dueño escriben y eliminan; el colaborador con la herramienta edita; el visitante solo lee. La clave `riesgos` debe estar en `Model\Usuario::HERRAMIENTAS` (lo comprueba `tests/run.php`).
- Migración: `database/migracion_riesgos.sql`.

## Permisos y eventos

- Las escrituras usan `proyecto_de_peticion(true)` (admin o cliente propietario o con el proyecto asignado).
- Las cuatro claves están en `Model\Usuario::HERRAMIENTAS` y en el menú (`views/templates/herramientas-proyecto.php`), así que el visitante solo las ve si se le habilitan.
- **Resumen matutino:** si el proyecto tiene activado el resumen de la obra (Integraciones → Telegram), `Classes\ResumenObra` incluye los contratos/fianzas por vencer, los equipos con servicio próximo y los riesgos altos abiertos. Dentro de cada herramienta también se ven siempre.

## Subcontratos (`Classes\Subcontratos`)

- `subcontratos`: subcontratista, partida, alcance, monto, retención %, anticipo %, fechas, estado y `compromiso_id`. Al guardar se crea/actualiza un compromiso (`compromisos.tipo = subcontrato`) con el monto; cerrar/cancelar libera el saldo.
- `subcontrato_documentos`: fianza, seguro de responsabilidad civil, permisos y alta de personal (obligatorios) más `otro`. Cada uno con archivo del Archivo, vencimiento y validación. `documentosEstado()` exige archivo, `validado = 1` y fecha no vencida.
- **Compuerta de pago:** `puedePagar()` bloquea aprobar/pagar una estimación si falta o venció un documento. Solo el admin puede autorizarla con `motivo_autorizacion` (≥10 caracteres); queda `autorizado_por`.
- `subcontrato_estimaciones`: subtotal, IVA, retención, amortización del anticipo, ajuste, bruto y neto, calculados con `Estimaciones::calcular()`. La amortización se topa al anticipo pendiente. Al aprobar/pagar se aplica el **neto** al compromiso (`Compromisos::aplicar`) y se registra como costo real; al editar se revierte el movimiento anterior.
- PDF en `/subcontratos/pdf?id=`. Permiso de visitante: solo lectura. Migración `database/migracion_subcontratos.sql`.

## Envíos y acuses (`Classes\Transmittals`)

- `transmittals`: número, fecha, de, asunto, mensaje, medio, fecha límite y estado (borrador/enviado/cerrado).
- `transmittal_documentos`: snapshot del archivo (`archivo_id`, nombre y `revision` al momento de enviar); si el archivo cambia después, el envío conserva la revisión enviada.
- `transmittal_destinatarios`: nombre, correo, token único de 40 hex y acuse (`pendiente/recibido/observado/rechazado`) con nombre, comentario, fecha e IP.
- `Transmittals::guardar()` solo permite editar en borrador; al pasar a enviado genera los tokens. `acuse()` valida token, impide doble acuse y cierra el envío cuando todos contestaron.
- Enlace público `/transmittals/acuse?t=TOKEN` (sin sesión, `publicoLayout`, noindex). El acuse no bloquea la obra; `Salud` avisa de envíos sin acuse o vencidos.
- UI en **Archivo → Envíos** (`/transmittals`), dentro del grupo de pestañas de Archivo. PDF en `/transmittals/pdf`.
- Migración `database/migracion_transmittals.sql`.

## Punch y cierre (`Classes\Punch`, `Model\Partida`)

- `Punch::partidasBloqueadas($pid)` cuenta pendientes con severidad **alta/crítica** y estado **abierto/en_proceso/corregido** por partida. Los menores y los cerrados/rechazados no cuentan.
- `Model\Partida::cerrar($pid,$id,$motivo)` cierra la partida (`partidas.cerrada`, `cerrada_por`, `cerrada_en`, `cierre_motivo`). Si hay pendientes graves exige un motivo de **≥10 caracteres**; el controlador solo permite la excepción a admin o dueño. `reabrir()` limpia el cierre.
- `Classes\Cierre::guardarItem()` rechaza marcar como **completado** un punto de categoría `punch` mientras haya partidas bloqueadas. `Cierre::resumen()` topa el avance en **99 %** y devuelve `punch_bloqueos`, `punch_partidas` y `bloqueado`.
- UI: candado en la fila de la partida en Programa, pantalla `/programa/partidas/cerrar`, aviso en Cierre y en el acta PDF, e indicador en Salud.
- Migración `database/migracion_punch_cierre.sql`.

## Calidad completa (`Classes\CalidadCompleta`, `Classes\CalidadExpediente`)

- `calidad_ensayos`: tipo, muestra, ubicación, fechas, laboratorio, norma, resultado, veredicto (`pendiente/aprobado/rechazado`), certificado del Archivo y actividad/plan opcionales.
- `calidad_nc`: código, hallazgo, causa, acción, responsable, compromiso, costo, estado (`abierta/en_correccion/cerrada`), evidencias, `movimiento_id`, cierre. `calidad_nc_acciones` guarda el seguimiento verificado.
- La NC formal abierta/en corrección se suma a `Calidad::bloqueosProyecto()`: congela el avance de su actividad igual que un punto no cumple.
- `CalidadCompleta::enviarCosto()` crea un `costos_movimientos` real por el costo y guarda `movimiento_id` (una sola vez).
- `CalidadExpediente::datos()` reúne ITP, puntos, ensayos y NC de la partida; `pdf()` genera el expediente y `cerrar()` lo guarda en `storage/calidad/<proyecto>/` con huella MD5. Un expediente cerrado se descarga congelado; `reabrir()` lo vuelve a borrador (solo admin/dueño).
- UI: pestañas **Planes ITP**, **Ensayos**, **No conformidades** y **Expedientes** en `/calidad`; ruta PDF `/calidad/expediente/pdf?partida=`.
- Migración `database/migracion_calidad_completa.sql`.
