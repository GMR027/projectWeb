# Ensamblador de documentos (Documentos y diseño)

_Se lee solo al tocar `classes/EnsamblePdf.php`, `models/Ensamble.php`, `controllers/EnsambleController.php` o `views/ensamblador-documentos/*`. No confundir con el «Ensamblador» de IA (`docs/ensamblador.md`)._

- Ruta `/ensamblador-documentos` (clave de visitante `ensamble`). Une documentos del **Redactor** y **PDF del Archivo** en un PDF no editable.
- Un ensamble es una **receta** (`ensambles.fuentes`, JSON `[{t: redactor|archivo, id}]`, en orden); el PDF no se guarda hasta pedirlo. Descargar/ver genera el PDF con las fuentes actuales (`EnsamblePdf::generar`: `Redactor::pdf` con SheetFlow refrescado + `pdfunite`, o Ghostscript si falta).
- **Guardar en Archivo** (`EnsamblePdf::guardarEnArchivo`) crea una fila de `archivos` ligada por `ensambles.archivo_id` y guarda en `firma` la huella de las fuentes (`EnsamblePdf::firma`: `actualizado` + md5 del contenido del Redactor, archivo/tamaño de los PDF, última edición de SheetFlow).
- **Actualización automática:** `Archivo::ruta()` llama `EnsamblePdf::refrescar`; si la huella cambió regenera el PDF en su sitio (ver, descargar, comparador…). Si falla se conserva la versión anterior. Guardar la receta de un ensamble ya en Archivo también lo regenera.
- Un ensamble no puede ser fuente de otro (los PDF ligados se excluyen en `disponibles`). Una fuente borrada se omite y se avisa en la lista.
- Eliminar el ensamble deja el PDF del Archivo como copia fija (`ON DELETE SET NULL`); eliminar el archivo suelta el vínculo. La descarga es un PDF común sin vínculo.
- Tabla `ensambles` en `database/migracion_ensamble_pdf.sql` (también en `schema.sql`). **Aplicar la migración en producción.** Requiere `pdfunite` (poppler-utils, ya en el Dockerfile) o `gs`.
