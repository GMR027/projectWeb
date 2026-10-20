-- Encabezado del MSR: estampa (lienzo) elegida; NULL = encabezado de siempre
ALTER TABLE msrs ADD COLUMN encabezado_id INT NULL AFTER columnas_json;
