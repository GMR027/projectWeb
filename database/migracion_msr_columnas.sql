-- MSR configurable: columnas elegidas, con nombre propio y en el orden del usuario (NULL = diseño clásico)
ALTER TABLE msrs ADD COLUMN columnas_json TEXT NULL AFTER descripcion;
-- Valores de las columnas de texto que agrega el usuario ({"txt_1":"..."})
ALTER TABLE msr_items ADD COLUMN extras_json TEXT NULL;
-- Color del título de cada partida propia del MSR (#rrggbb; vacío = el de siempre)
ALTER TABLE msr_partidas ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '' AFTER titulo;
