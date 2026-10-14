-- Hitos y nombre interno de tablas adicionales
ALTER TABLE actividades ADD COLUMN hito TINYINT NOT NULL DEFAULT 0 AFTER pta;
ALTER TABLE vplan_tablas ADD COLUMN nombre_interno VARCHAR(150) DEFAULT NULL AFTER titulo;
