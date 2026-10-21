# Directorio (Administración)

Ruta `/directorio` (pestañas por `?tab=`). Reúne en una pantalla lo que varias herramientas compartían como texto libre. Código: `controllers/DirectorioController.php`, `classes/Catalogo.php`, `views/directorio/index.php`.

| Pestaña | Datos | Dónde se usan |
|---|---|---|
| Personal del proyecto | tabla `personal` + `personal_contacto` (tipo directivo/administrativo/operativo, cargo, empresa, teléfono, correo, dirección, RFC, notas; formularios de `ReporteController::personal*`). Guardar con `Personal::guardarContacto()` (upsert; **nunca** `REPLACE INTO personal_contacto`, borraría los demás campos) | Reporte diario, Minutas |
| Personal del contratista | `personal_contratista` (formularios de `MinutaController::contratista*`) | Minutas |
| Departamentos | `bitacora_departamentos` + lo que ya está en uso | Bitácora, Minutas, Redactor, SheetFlow, Submittals, Personal, Evidencias |
| Categorías | `archivo_categorias` (Archivo), `catalogo_categorias` (Punch, Evidencias) | Archivo, Punch, Evidencias |
| Especialidades | `especialidades` (`EspecialidadController`) | Materiales, Submittals |

Reglas:
- Cada herramienta sigue guardando el departamento/categoría **como texto**; el catálogo solo alimenta sus listas. `Catalogo::departamentos($pid)` = catálogo ∪ valores en uso (por eso nada deja de verse al migrar).
- `Catalogo::renombrarDepartamento/Categoria` cambia el texto en todas las tablas de `USO_DEPARTAMENTO` / `AMBITOS`; si el nombre nuevo ya existe, se unen. Eliminar solo se permite sin uso.
- Para dar de alta un departamento o categoría en una herramienta nueva: añade su tabla a `Catalogo::USO_DEPARTAMENTO` (o su ámbito a `AMBITOS`) y usa `Catalogo::departamentos()` / `categorias()` en su formulario.
- Los formularios de personal y especialidades aceptan `volver=directorio` para regresar al Directorio al guardar.
- Migraciones: `database/migracion_personal_datos.sql` (campos del personal) y `database/migracion_directorio.sql` (`catalogo_categorias`) + columnas `evidencias.departamento/categoria` (ver `aplicar_migraciones_cluster.sh`).
