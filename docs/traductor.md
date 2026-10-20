# Traductor (español ↔ inglés)

_Se lee solo al tocar `classes/Traductor.php`, `controllers/TraductorController.php`, `bin/traductor.py` o `views/traductor/`._

- Herramienta `traductor` (menú «Documentos y diseño», clave en `Usuario::HERRAMIENTAS`, sección `#traductor` del manual). Página `/traductor`; API `POST /api/traductor/texto` y `POST /api/traductor/documento` (descarga directa; nada se guarda en el servidor).
- **Motor local, sin configuración:** `bin/traductor.py` (CTranslate2 + SentencePiece) con los modelos Opus-MT de Helsinki-NLP (CC-BY 4.0) convertidos a CTranslate2. Se instala con `bin/instalar_traductor.sh` en `storage/traductor/` (≈ 400 MB: `venv/`, `es_en/model`, `en_es/model`); en Docker se instala en `/opt/traductor` (`TRADUCTOR_DIR`) porque `storage/` es un volumen. `TRADUCTOR_PYTHON` cambia el Python usado.
- **Motor alterno:** si se define `TRADUCTOR_URL` (servidor compatible con LibreTranslate, más `TRADUCTOR_API_KEY` opcional) se usa ese en lugar del local.
- Si no hay motor, la página muestra un aviso (el admin ve cómo instalarlo) y los botones quedan deshabilitados.
- Cada llamada al motor local carga el modelo (~2 s), por eso `Traductor::traducir` manda lotes grandes (400 textos / 60 000 caracteres). Se traduce frase por frase (el modelo omite frases si recibe varias) y se cachean textos repetidos.
- **Documentos** (≤ 20 MB, `EscanerArchivos::revisar` antes; requieren `puede_descargar()` o admin, nunca el demo): DOCX/PPTX/ODT/ODP se reescriben dentro del zip por párrafo (texto en el primer fragmento, el resto queda vacío: se pierde el formato interno del párrafo); XLSX/ODS/CSV con PhpSpreadsheet (solo celdas de texto, no fórmulas); TXT/MD por línea; PDF → PDF nuevo solo con texto (`pdftotext` + Dompdf, sin OCR). DOC/RTF/XLS/PPT se convierten antes con LibreOffice (`soffice`, si no está se avisa) y se entregan como DOCX/XLSX/PPTX. Tope de 600 000 caracteres por documento.
- ODF: `<text:s/>` se convierte en espacios antes de unir fragmentos.
- Pruebas: servidor falso compatible con LibreTranslate en `TRADUCTOR_URL`, o el motor local; temporales en `sys_get_temp_dir()` y se borran siempre.
